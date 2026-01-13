<?php

namespace App\Http\Controllers;

use App\Models\Cartucho;
use App\Models\CartuchoDetalle;
use App\Models\Colaborador;
use App\Models\Producto;
use App\Models\ProductoSerie;
use App\Models\ProductoExistencia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class CartuchoController extends Controller
{
    private function tenantId(): int
    {
        return (int) session('empresa_activa', auth()->user()?->empresa_id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->tenantId();

        $q = trim((string) $request->query('q', $request->query('search', '')));

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) $perPage = 20;

        $colCols = [
            'nombre'           => Schema::hasColumn('colaboradores', 'nombre'),
            'apellidos'        => Schema::hasColumn('colaboradores', 'apellidos'),
            'apellido'         => Schema::hasColumn('colaboradores', 'apellido'),
            'apellido_paterno' => Schema::hasColumn('colaboradores', 'apellido_paterno'),
            'apellido_materno' => Schema::hasColumn('colaboradores', 'apellido_materno'),
            'primer_apellido'  => Schema::hasColumn('colaboradores', 'primer_apellido'),
            'segundo_apellido' => Schema::hasColumn('colaboradores', 'segundo_apellido'),
        ];

        $cartuchos = Cartucho::query()
            ->deEmpresa($tenantId)
            ->with([
                'colaborador:id,nombre,apellidos,unidad_servicio_id',
                'colaborador.unidadServicio:id,nombre',
                'producto:id,nombre,marca,modelo',
                'realizadoPor:id,name',
                'firmaRealizoUser:id,name',
                'firmaRecibioColaborador:id,nombre,apellidos',
                'detalles.producto:id,nombre,marca,modelo,sku,especificaciones,tipo',
            ])
            ->when($q !== '', function ($query) use ($q, $colCols) {
                $like = '%' . $q . '%';

                $query->where(function ($w) use ($like, $colCols) {

                    // 1) Folio
                    $w->where('folio', 'like', $like);

                    // 2) Colaborador (solo columnas existentes)
                    $w->orWhereHas('colaborador', function ($c) use ($like, $colCols) {
                        $c->where(function ($cx) use ($like, $colCols) {

                            if ($colCols['nombre']) {
                                $cx->orWhere('nombre', 'like', $like);
                            }

                            if ($colCols['apellidos']) {
                                $cx->orWhere('apellidos', 'like', $like);
                            }

                            if ($colCols['apellido']) {
                                $cx->orWhere('apellido', 'like', $like);
                            }

                            if ($colCols['apellido_paterno']) {
                                $cx->orWhere('apellido_paterno', 'like', $like);
                            }

                            if ($colCols['apellido_materno']) {
                                $cx->orWhere('apellido_materno', 'like', $like);
                            }

                            if ($colCols['primer_apellido']) {
                                $cx->orWhere('primer_apellido', 'like', $like);
                            }

                            if ($colCols['segundo_apellido']) {
                                $cx->orWhere('segundo_apellido', 'like', $like);
                            }
                        });
                    });

                    // 3) Unidad de servicio (nombre)
                    $w->orWhereHas('colaborador.unidadServicio', function ($us) use ($like) {
                        $us->where('nombre', 'like', $like);
                    });

                    // 4) Equipo (producto)
                    $w->orWhereHas('producto', function ($p) use ($like) {
                        $p->where(function ($px) use ($like) {
                            $px->where('nombre', 'like', $like)
                            ->orWhere('marca', 'like', $like)
                            ->orWhere('modelo', 'like', $like);
                        });
                    });

                    // 5) Realizado por (usuario)
                    $w->orWhereHas('realizadoPor', function ($u) use ($like) {
                        $u->where('name', 'like', $like);
                    });

                    // ✅ 6) SKU en los detalles (consumibles solicitados)
                    $w->orWhereHas('detalles.producto', function ($p) use ($like) {
                        $p->where(function ($px) use ($like) {
                            $px->where('sku', 'like', $like);

                            // (opcional) también permitir buscar por nombre/marca/modelo del consumible del detalle
                            // ->orWhere('nombre', 'like', $like)
                            // ->orWhere('marca', 'like', $like)
                            // ->orWhere('modelo', 'like', $like);
                        });
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $cartuchos->appends($request->query());

        if ($request->ajax() && $request->query('partial') == '1') {
            return view('cartuchos.partials.table', compact('cartuchos'));
        }

        return view('cartuchos.index', compact('cartuchos', 'q', 'perPage'));
    }

    public function create()
    {
        $tenantId = $this->tenantId();

        // ===== Colaboradores (tenant)
        $colabQ = Colaborador::query()
            ->where('activo', 1)
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->select(['id', 'nombre', 'apellidos', 'unidad_servicio_id']);

        if (Schema::hasColumn('colaboradores', 'empresa_id')) {
            $colabQ->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores', 'empresa_tenant_id')) {
            $colabQ->where('empresa_tenant_id', $tenantId);
        }

        $colaboradores = $colabQ->get();

        // ===== Usuarios admin
        $users = User::role('Administrador')
            ->orderBy('name')
            ->get(['id', 'name']);

        // ===== EQUIPO: impresora/multi (producto_id) + TODAS sus SERIES
        $equiposQ = Producto::query()
            ->where('activo', 1)
            ->select(['id', 'nombre', 'marca', 'modelo', 'tipo', 'activo']);

        if (Schema::hasColumn('productos', 'empresa_tenant_id')) {
            $equiposQ->where('empresa_tenant_id', $tenantId);
        }

        if (Schema::hasColumn('productos', 'tipo')) {
            $equiposQ->where(function ($q) {
                $q->whereRaw('LOWER(tipo) = ?', ['impresora'])
                ->orWhereRaw('LOWER(tipo) = ?', ['impresora/multifuncional'])
                ->orWhereRaw('LOWER(tipo) LIKE ?', ['%multifuncional%']);
            });
        }

        // ✅ Cargar TODAS las series (sin filtrar por estado)
        $equiposQ->with(['series' => function ($q) use ($tenantId) {
            $q->select(['id', 'producto_id', 'serie', 'estado', 'unidad_servicio_id'])
            ->when(
                Schema::hasColumn('producto_series', 'empresa_tenant_id'),
                fn ($w) => $w->where('empresa_tenant_id', $tenantId)
            )
            ->orderBy('serie', 'asc');
        }]);

        // Lista original de productos (la dejamos intacta por compatibilidad)
        $productos = $equiposQ->orderBy('nombre')->get();

        // ✅ APLANAR: 1 opción por CADA serie (esto es lo que usarás en el select)
        $equiposOptions = collect();

        foreach ($productos as $p) {
            $titulo = trim(
                ($p->nombre ?? '') . ' ' . ($p->marca ?? '') . ' ' . ($p->modelo ?? '')
            );

            if ($p->relationLoaded('series') && $p->series && $p->series->count()) {
                foreach ($p->series as $s) {
                    $equiposOptions->push([
                        'producto_id'        => $p->id,
                        'serie_id'           => $s->id,
                        'serie'              => trim((string) $s->serie),
                        'estado'             => $s->estado ?? null,
                        'unidad_servicio_id' => $s->unidad_servicio_id ?? null,
                        'text'               => $titulo,
                    ]);
                }
            } else {
                // Si NO tiene series, lo mostramos una vez (serie vacía)
                $equiposOptions->push([
                    'producto_id'        => $p->id,
                    'serie_id'           => null,
                    'serie'              => '',
                    'estado'             => null,
                    'unidad_servicio_id' => null,
                    'text'               => $titulo,
                ]);
            }
        }

        /**
         * ===== CARTUCHOS: consumibles SOLO con existencia > 0 (tenant)
         * Requiere relación en Producto:
         * public function existencia(){ return $this->hasOne(ProductoExistencia::class,'producto_id','id'); }
         */
        $productosCartucho = Producto::query()
            ->where('activo', 1)
            // ✅ IMPORTANTE: traer especificaciones para poder pintar color (sin romper si no existe la columna)
            ->select(['id', 'nombre', 'marca', 'modelo', 'sku', 'tipo', 'activo'])
            ->when(
                Schema::hasColumn('productos', 'especificaciones'),
                fn ($q) => $q->addSelect('especificaciones')
            )
            ->when(
                Schema::hasColumn('productos', 'empresa_tenant_id'),
                fn ($q) => $q->where('empresa_tenant_id', $tenantId)
            )
            // solo consumibles
            ->when(Schema::hasColumn('productos', 'tipo'), function ($q) {
                $q->whereRaw('LOWER(tipo) = ?', ['consumible']);
            }, function ($q) {
                if (Schema::hasColumn('productos', 'es_consumible')) {
                    $q->where('es_consumible', 1);
                } elseif (Schema::hasColumn('productos', 'consumible')) {
                    $q->where('consumible', 1);
                } else {
                    $q->where(function ($w) {
                        $w->whereRaw('LOWER(nombre) LIKE ?', ['%cartucho%'])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', ['%toner%'])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', ['%tóner%'])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', ['%tinta%']);
                    });
                }
            })
            // ✅ SOLO los que tienen existencia > 0 en este tenant
            ->whereHas('existencia', function ($q) use ($tenantId) {
                $q->where('empresa_tenant_id', $tenantId)
                ->where('cantidad', '>', 0);
            })
            // (opcional) precargar existencia por si la quieres mostrar en el select
            ->with(['existencia' => function ($q) use ($tenantId) {
                $q->select('id', 'producto_id', 'empresa_tenant_id', 'cantidad')
                ->where('empresa_tenant_id', $tenantId);
            }])
            ->orderBy('nombre')
            ->get();

        return view('cartuchos.create', compact(
            'colaboradores',
            'productos',          // ✅ se mantiene, no afectas nada existente
            'equiposOptions',     // ✅ NUEVO: para que el select muestre 1 por serie
            'users',
            'productosCartucho'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenantId();

        // Colaborador exists por tenant
        $colExists = Rule::exists('colaboradores', 'id');
        if (Schema::hasColumn('colaboradores', 'empresa_id')) {
            $colExists = $colExists->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores', 'empresa_tenant_id')) {
            $colExists = $colExists->where('empresa_tenant_id', $tenantId);
        }

        $adminIds = User::role('Administrador')->pluck('id')->all();

        $request->validate([
            'fecha_solicitud' => ['required', 'date'],
            'colaborador_id'  => ['required', $colExists],

            // Equipo (impresora/multi)
            'producto_id'     => [
                'required',
                'integer',
                Rule::exists('productos', 'id')
                    ->when(Schema::hasColumn('productos', 'empresa_tenant_id'), fn ($r) => $r->where('empresa_tenant_id', $tenantId))
            ],

            'realizado_por'   => ['required', Rule::in($adminIds)],

            // firmas reales en tu tabla cartuchos
            'firma_realizo'   => ['required', Rule::in($adminIds)],
            'firma_recibio'   => ['required', $colExists],

            // ===== DETALLES: productos cartucho + cantidad
            'items'               => ['required', 'array', 'min:1'],
            'items.*.producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')
                    ->when(Schema::hasColumn('productos', 'empresa_tenant_id'), fn ($r) => $r->where('empresa_tenant_id', $tenantId))
            ],
            'items.*.cantidad'    => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        DB::transaction(function () use ($request, $tenantId) {

            // ✅ 1) Agrupar cantidades por consumible (por si repites el mismo)
            $reqByProducto = [];
            foreach ($request->items as $row) {
                $pid = (int)($row['producto_id'] ?? 0);
                $qty = (int)($row['cantidad'] ?? 0);
                if ($pid > 0 && $qty > 0) {
                    $reqByProducto[$pid] = ($reqByProducto[$pid] ?? 0) + $qty;
                }
            }

            // ✅ 2) Validar existencias (lock)
            foreach ($reqByProducto as $pid => $qtySolicitada) {

                $ex = ProductoExistencia::query()
                    ->deEmpresa($tenantId)
                    ->where('producto_id', $pid)
                    ->lockForUpdate()
                    ->first();

                $disponible = (int)($ex->cantidad ?? 0);

                if ($disponible < $qtySolicitada) {
                    throw ValidationException::withMessages([
                        'items' => ["No hay existencia suficiente para el consumible (ID {$pid}). Disponible: {$disponible}, solicitado: {$qtySolicitada}."],
                    ]);
                }
            }

            // ✅ 3) Crear solicitud
            $folio = $this->nextFolio($tenantId);

            $cartucho = Cartucho::create([
                'empresa_tenant_id' => $tenantId,
                'folio'             => $folio,
                'fecha_solicitud'   => $request->fecha_solicitud,
                'colaborador_id'    => $request->colaborador_id,
                'producto_id'       => $request->producto_id,   // impresora/multi
                'realizado_por'     => $request->realizado_por,
                'firma_realizo'     => $request->firma_realizo,
                'firma_recibio'     => $request->firma_recibio,
            ]);

            foreach ($request->items as $row) {
                CartuchoDetalle::create([
                    'cartucho_id' => $cartucho->id,
                    'producto_id' => $row['producto_id'], // consumible
                    'cantidad'    => $row['cantidad'],
                ]);
            }

            // ✅ 4) Descontar existencias
            foreach ($reqByProducto as $pid => $qtySolicitada) {
                $ex = ProductoExistencia::query()
                    ->deEmpresa($tenantId)
                    ->where('producto_id', $pid)
                    ->lockForUpdate()
                    ->first();

                $ex->cantidad = (int)$ex->cantidad - (int)$qtySolicitada;
                $ex->save();
            }
        });

        return redirect()->route('cartuchos.index')->with('created', 'Entrega de Cartuchos Creada.');
    }

    public function edit(Cartucho $cartucho)
    {
        $tenantId = $this->tenantId();

        abort_unless((int) $cartucho->empresa_tenant_id === (int) $tenantId, 404);

        // ✅ cargar detalles (para armar itemsDB y para incluirlos en el select aunque tengan existencia 0)
        $cartucho->load(['detalles.producto']);

        // ===== Colaboradores (tenant) - incluye unidad_servicio_id
        $colabQ = Colaborador::query()
            ->where('activo', 1)
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->select(['id', 'nombre', 'apellidos', 'unidad_servicio_id']);

        if (Schema::hasColumn('colaboradores', 'empresa_id')) {
            $colabQ->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores', 'empresa_tenant_id')) {
            $colabQ->where('empresa_tenant_id', $tenantId);
        }

        $colaboradores = $colabQ->get();

        // ===== Usuarios admin
        $users = User::role('Administrador')
            ->orderBy('name')
            ->get(['id', 'name']);

        // ===== Equipos (productos impresora/multi) + series
        $equiposQ = Producto::query()
            ->where('activo', 1)
            ->select(['id', 'nombre', 'marca', 'modelo', 'tipo', 'activo'])
            ->when(
                Schema::hasColumn('productos', 'empresa_tenant_id'),
                fn ($q) => $q->where('empresa_tenant_id', $tenantId)
            );

        if (Schema::hasColumn('productos', 'tipo')) {
            $equiposQ->where(function ($q) {
                $q->whereRaw('LOWER(tipo) = ?', ['impresora'])
                ->orWhereRaw('LOWER(tipo) = ?', ['impresora/multifuncional'])
                ->orWhereRaw('LOWER(tipo) LIKE ?', ['%multifuncional%']);
            });
        }

        $equiposQ->with(['series' => function ($q) use ($tenantId) {
            $q->select(['id', 'producto_id', 'serie', 'estado', 'unidad_servicio_id'])
            ->when(
                Schema::hasColumn('producto_series', 'empresa_tenant_id'),
                fn ($w) => $w->where('empresa_tenant_id', $tenantId)
            )
            ->orderBy('serie', 'asc');
        }]);

        $productos = $equiposQ->orderBy('nombre')->get();

        $equiposOptions = collect();
        foreach ($productos as $p) {
            $titulo = trim(($p->nombre ?? '') . ' ' . ($p->marca ?? '') . ' ' . ($p->modelo ?? ''));

            if ($p->relationLoaded('series') && $p->series && $p->series->count()) {
                foreach ($p->series as $s) {
                    $equiposOptions->push([
                        'producto_id'        => $p->id,
                        'serie_id'           => $s->id,
                        'serie'              => trim((string) $s->serie),
                        'text'               => $titulo,
                        'unidad_servicio_id' => $s->unidad_servicio_id ?? null,
                    ]);
                }
            } else {
                $equiposOptions->push([
                    'producto_id'        => $p->id,
                    'serie_id'           => null,
                    'serie'              => '',
                    'text'               => $titulo,
                    'unidad_servicio_id' => null,
                ]);
            }
        }

        // ✅ IDs de consumibles que YA están en la solicitud (para que no se “pierdan” en el edit)
        $detalleIds = $cartucho->detalles
            ->pluck('producto_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // ===== Consumibles con existencia > 0 (pero también incluir los del detalle aunque estén en 0)
        $productosCartucho = Producto::query()
            ->where('activo', 1)
            ->select(['id', 'nombre', 'marca', 'modelo', 'sku', 'tipo', 'activo'])
            // ✅ traer especificaciones para pintar color en el edit.blade (data-cc desde especificaciones->color)
            ->when(
                Schema::hasColumn('productos', 'especificaciones'),
                fn ($q) => $q->addSelect('especificaciones')
            )
            ->when(
                Schema::hasColumn('productos', 'empresa_tenant_id'),
                fn ($q) => $q->where('empresa_tenant_id', $tenantId)
            )
            ->when(Schema::hasColumn('productos', 'tipo'), function ($q) {
                $q->whereRaw('LOWER(tipo) = ?', ['consumible']);
            })
            ->where(function ($q) use ($tenantId, $detalleIds) {
                // disponibles (>0)
                $q->whereHas('existencia', function ($w) use ($tenantId) {
                    $w->where('empresa_tenant_id', $tenantId)
                    ->where('cantidad', '>', 0);
                });

                // o los que ya están en la solicitud (aunque tengan 0)
                if (!empty($detalleIds)) {
                    $q->orWhereIn('id', $detalleIds);
                }
            })
            ->orderBy('nombre')
            ->get();

        // ✅ items de BD para precargar (si no hay old('items'))
        $itemsDB = $cartucho->detalles->map(fn ($d) => [
            'producto_id' => $d->producto_id,
            'cantidad'    => $d->cantidad,
        ])->values();

        return view('cartuchos.edit', compact(
            'cartucho',
            'colaboradores',
            'productos',
            'equiposOptions',
            'users',
            'productosCartucho',
            'itemsDB'
        ));
    }

    public function update(Request $request, Cartucho $cartucho)
    {
        $tenantId = $this->tenantId();

        abort_unless((int)$cartucho->empresa_tenant_id === (int)$tenantId, 404);

        // Colaborador exists por tenant
        $colExists = Rule::exists('colaboradores', 'id');
        if (Schema::hasColumn('colaboradores', 'empresa_id')) {
            $colExists = $colExists->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores', 'empresa_tenant_id')) {
            $colExists = $colExists->where('empresa_tenant_id', $tenantId);
        }

        $adminIds = User::role('Administrador')->pluck('id')->all();

        $request->validate([
            'fecha_solicitud' => ['required', 'date'],
            'colaborador_id'  => ['required', $colExists],

            // Equipo (impresora/multi)
            'producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')
                    ->when(
                        Schema::hasColumn('productos', 'empresa_tenant_id'),
                        fn ($r) => $r->where('empresa_tenant_id', $tenantId)
                    ),
            ],

            'realizado_por' => ['required', Rule::in($adminIds)],

            'firma_realizo' => ['required', Rule::in($adminIds)],
            'firma_recibio' => ['required', $colExists],

            // Detalles
            'items'               => ['required', 'array', 'min:1'],
            'items.*.producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')
                    ->when(
                        Schema::hasColumn('productos', 'empresa_tenant_id'),
                        fn ($r) => $r->where('empresa_tenant_id', $tenantId)
                    ),
            ],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        DB::transaction(function () use ($request, $tenantId, $cartucho) {

            // Cargar detalles actuales
            $cartucho->load('detalles');

            // 1) Agrupar cantidades NUEVAS por consumible
            $newByProducto = [];
            foreach ($request->items as $row) {
                $pid = (int)($row['producto_id'] ?? 0);
                $qty = (int)($row['cantidad'] ?? 0);
                if ($pid > 0 && $qty > 0) {
                    $newByProducto[$pid] = ($newByProducto[$pid] ?? 0) + $qty;
                }
            }

            // 2) Agrupar cantidades VIEJAS por consumible (lo que ya estaba descontado)
            $oldByProducto = [];
            foreach ($cartucho->detalles as $d) {
                $pid = (int)$d->producto_id;
                $qty = (int)$d->cantidad;
                if ($pid > 0 && $qty > 0) {
                    $oldByProducto[$pid] = ($oldByProducto[$pid] ?? 0) + $qty;
                }
            }

            // 3) Productos involucrados (unión)
            $allPids = collect(array_keys($newByProducto))
                ->merge(array_keys($oldByProducto))
                ->unique()
                ->values()
                ->all();

            // 4) Bloquear existencias de TODOS los involucrados
            $existencias = ProductoExistencia::query()
                ->deEmpresa($tenantId)
                ->whereIn('producto_id', $allPids)
                ->lockForUpdate()
                ->get()
                ->keyBy('producto_id');

            // Si alguno no tiene registro de existencia, lo tratamos como 0
            foreach ($allPids as $pid) {
                if (!$existencias->has($pid)) {
                    throw ValidationException::withMessages([
                        'items' => ["El consumible (ID {$pid}) no tiene registro de existencia en esta empresa."],
                    ]);
                }
            }

            // 5) PRIMERO: devolver al stock lo viejo
            foreach ($oldByProducto as $pid => $qtyOld) {
                $ex = $existencias[$pid];
                $ex->cantidad = (int)$ex->cantidad + (int)$qtyOld;
            }

            // 6) Validar que alcance para lo nuevo (ya con lo viejo “regresado”)
            foreach ($newByProducto as $pid => $qtyNew) {
                $ex = $existencias[$pid];
                $disponible = (int)$ex->cantidad;

                if ($disponible < (int)$qtyNew) {
                    throw ValidationException::withMessages([
                        'items' => ["No hay existencia suficiente para el consumible (ID {$pid}). Disponible: {$disponible}, solicitado: {$qtyNew}."],
                    ]);
                }
            }

            // 7) Ahora descontar lo nuevo
            foreach ($newByProducto as $pid => $qtyNew) {
                $ex = $existencias[$pid];
                $ex->cantidad = (int)$ex->cantidad - (int)$qtyNew;
            }

            // 8) Guardar existencias
            foreach ($existencias as $ex) {
                $ex->save();
            }

            // 9) Actualizar cabecera cartucho
            $cartucho->update([
                'fecha_solicitud' => $request->fecha_solicitud,
                'colaborador_id'  => $request->colaborador_id,
                'producto_id'     => $request->producto_id, // impresora/multi
                'realizado_por'   => $request->realizado_por,
                'firma_realizo'   => $request->firma_realizo,
                'firma_recibio'   => $request->firma_recibio,
            ]);

            // 10) Reemplazar detalles
            CartuchoDetalle::where('cartucho_id', $cartucho->id)->delete();

            foreach ($request->items as $row) {
                CartuchoDetalle::create([
                    'cartucho_id' => $cartucho->id,
                    'producto_id' => (int)$row['producto_id'],
                    'cantidad'    => (int)$row['cantidad'],
                ]);
            }
        });

        return redirect()->route('cartuchos.index')->with('updated', 'Entrega de Cartuchos Actualizada.');
    }

    public function show(Cartucho $cartucho)
    {
        $tenantId = $this->tenantId();

        abort_unless((int) $cartucho->empresa_tenant_id === (int) $tenantId, 404);

        // Cargar relaciones necesarias para el show
        $cartucho->load([
            'colaborador:id,nombre,apellidos,unidad_servicio_id',
            'colaborador.unidadServicio:id,nombre',
            'producto:id,nombre,marca,modelo',
            'realizadoPor:id,name',
            'firmaRealizoUser:id,name',
            'firmaRecibioColaborador:id,nombre,apellidos',
            'detalles.producto:id,nombre,marca,modelo,sku,especificaciones,tipo',
        ]);

        return view('cartuchos.show', compact('cartucho'));
    }

    public function destroy(Cartucho $cartucho)
    {
        $tenantId = $this->tenantId();

        abort_unless((int)$cartucho->empresa_tenant_id === (int)$tenantId, 404);

        DB::transaction(function () use ($cartucho, $tenantId) {

            // 1) Devolver existencias de los consumibles
            $cartucho->load('detalles');

            foreach ($cartucho->detalles as $detalle) {
                $ex = ProductoExistencia::query()
                    ->deEmpresa($tenantId)
                    ->where('producto_id', $detalle->producto_id)
                    ->lockForUpdate()
                    ->first();

                if ($ex) {
                    $ex->cantidad += (int)$detalle->cantidad;
                    $ex->save();
                }
            }

            // 2) Borrar detalles
            CartuchoDetalle::where('cartucho_id', $cartucho->id)->delete();

            // 3) Borrar firmas si existen
            if (!empty($cartucho->firma_colaborador_path)) {
                Storage::disk('public')->delete($cartucho->firma_colaborador_path);
            }

            // 4) Borrar cartucho
            $cartucho->delete();
        });

        return redirect()
            ->route('cartuchos.index')
            ->with('deleted', 'Entrega de Cartuchos Eliminada Correctamente.');
    }

    private function nextFolio(int $tenantId): string
    {
        $prefix = 'SEC-';

        $last = Cartucho::query()
            ->where('empresa_tenant_id', $tenantId)
            ->where('folio', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('folio');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int)$m[1]) + 1;
        }

        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    public function pdf(Cartucho $cartucho)
    {
        $cartucho->load([
            'colaborador',
            'producto',
            'detalles.producto',
            'realizadoPor',
            'firmaRealizoUser',
        ]);

        $firmaRecibioPath = $cartucho->firma_colaborador_path
            ? public_path('storage/'.ltrim($cartucho->firma_colaborador_path, '/'))
            : null;

        $firmaRecibioB64 = null;
        if ($firmaRecibioPath && is_file($firmaRecibioPath)) {
            $ext = strtolower(pathinfo($firmaRecibioPath, PATHINFO_EXTENSION));
            $mime = match($ext) {
                'jpg','jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/png',
            };
            $firmaRecibioB64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($firmaRecibioPath));
        }

        $pdf = Pdf::loadView('cartuchos.pdf', compact('cartucho'))
            ->setPaper('letter', 'portrait');

        $folio = $cartucho->folio ?? ('CARTUCHO-'.$cartucho->id);

        return $pdf->stream("Salida-cartuchos-{$folio}.pdf");
    }

    public function pdfPublico(string $token)
    {
        $hash = hash('sha256', $token);

        $cartucho = Cartucho::query()
            ->with([
                'colaborador',
                'colaborador.unidadServicio',
                'producto',
                'detalles.producto',
                'realizadoPor',
                'firmaRealizoUser',
            ])
            ->where('firma_token', $hash)
            ->whereNotNull('firma_token_expires_at')
            ->where('firma_token_expires_at', '>=', now())
            ->firstOrFail();

        // Usa la MISMA vista pdf que ya tienes
        $pdf = Pdf::loadView('cartuchos.pdf', compact('cartucho'))
            ->setPaper('letter', 'portrait');

        $folio = $cartucho->folio ?? ('CARTUCHO-'.$cartucho->id);

        // ✅ stream para poder embeber en iframe
        return $pdf->stream("Salida-cartuchos-{$folio}.pdf");
    }

    public function emitirFirma(Cartucho $cartucho)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cartucho->empresa_tenant_id === (int) $tenantId, 404);

        $token = Str::random(48);

        $cartucho->update([
            'firma_token' => hash('sha256', $token),
            'firma_token_expires_at' => now()->addHours(48),
        ]);

        $url = route('cartuchos.firma.publica', ['token' => $token]);

        return redirect()
            ->route('cartuchos.show', $cartucho)
            ->with('firma_link', $url);
    }

    public function firmarEnSitio(Request $request, Cartucho $cartucho)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cartucho->empresa_tenant_id === (int) $tenantId, 404);

        $request->validate([
            'firma_data' => ['required', 'string'],
        ]);

        $data = $request->input('firma_data');

        // Esperamos "data:image/png;base64,...."
        if (!preg_match('/^data:image\/(\w+);base64,/', $data, $m)) {
            throw ValidationException::withMessages(['firma_data' => 'Formato de firma inválido.']);
        }

        $ext = strtolower($m[1] ?? 'png');
        if (!in_array($ext, ['png','jpg','jpeg','webp'])) $ext = 'png';

        $bin = base64_decode(substr($data, strpos($data, ',') + 1));
        if ($bin === false) {
            throw ValidationException::withMessages(['firma_data' => 'No se pudo decodificar la firma.']);
        }

        // Guardar en storage/app/public/cartuchos/firmas/
        $name = "cartuchos/firmas/cartucho-{$cartucho->id}-".now()->format('YmdHis').".{$ext}";
        Storage::disk('public')->put($name, $bin);

        $cartucho->update([
            'firma_colaborador_path' => $name,
            'firma_colaborador_at'   => now(),

            // si había link abierto, lo invalidamos
            'firma_token' => null,
            'firma_token_expires_at' => null,
        ]);

        return back()->with('ok', 'Firma guardada correctamente.');
    }

    public function firmaPublica(string $token)
    {
        $hash = hash('sha256', $token);

        $cartucho = Cartucho::query()
            ->where('firma_token', $hash)
            ->whereNotNull('firma_token_expires_at')
            ->where('firma_token_expires_at', '>=', now())
            ->with([
                'colaborador:id,nombre,apellidos',
            ])
            ->firstOrFail();

        $pdfUrl = route('cartuchos.firma.publica.pdf', ['token' => $token]);

        $thumbUrl = route('cartuchos.firma.publica.thumb', ['token' => $token]);

        return view('cartuchos.firma-publica', compact('cartucho', 'token', 'pdfUrl', 'thumbUrl'));
    }

    public function firmaPublicaStore(Request $request, string $token)
    {
        $request->validate([
            'firma_data' => ['required', 'string'],
        ]);

        $hash = hash('sha256', $token);

        $cartucho = Cartucho::query()
            ->where('firma_token', $hash)
            ->whereNotNull('firma_token_expires_at')
            ->where('firma_token_expires_at', '>=', now())
            ->firstOrFail();

        $data = $request->input('firma_data');

        if (!preg_match('/^data:image\/(\w+);base64,/', $data, $m)) {
            throw ValidationException::withMessages(['firma_data' => 'Formato de firma inválido.']);
        }

        $ext = strtolower($m[1] ?? 'png');
        if (!in_array($ext, ['png','jpg','jpeg','webp'])) $ext = 'png';

        $bin = base64_decode(substr($data, strpos($data, ',') + 1));
        if ($bin === false) {
            throw ValidationException::withMessages(['firma_data' => 'No se pudo decodificar la firma.']);
        }

        $name = "cartuchos/firmas/cartucho-{$cartucho->id}-" . now()->format('YmdHis') . ".{$ext}";
        Storage::disk('public')->put($name, $bin);

        // ✅ Generar URL firmada para la pantalla "Gracias"
        $okUrl = URL::temporarySignedRoute(
            'cartuchos.firma.publica.ok',
            now()->addMinutes(30),
            ['cartucho' => $cartucho->id]
        );

        // ✅ Guardar firma + invalidar token
        $cartucho->update([
            'firma_colaborador_path' => $name,
            'firma_colaborador_at'   => now(),
            'firma_token'            => null,
            'firma_token_expires_at' => null,
        ]);

        return redirect($okUrl);
    }

    public function firmaPublicaOk(Request $request, Cartucho $cartucho)
    {
        // No auth. Está protegido por URL firmada (signed middleware)
        $cartucho->load(['colaborador:id,nombre,apellidos']);

        return view('cartuchos.firma-publica-ok', compact('cartucho'));
    }

    public function destroyFirma(Cartucho $cartucho)
    {
        $tenantId = $this->tenantId();
        abort_unless((int) $cartucho->empresa_tenant_id === (int) $tenantId, 404);

        if (!empty($cartucho->firma_colaborador_path)) {
            Storage::disk('public')->delete($cartucho->firma_colaborador_path);
        }

        $cartucho->update([
            'firma_colaborador_path' => null,
            'firma_colaborador_at'   => null,
            'firma_token' => null,
            'firma_token_expires_at' => null,
        ]);

        return back()->with('ok', 'Firma eliminada.');
    }

    public function thumbPublico(string $token)
    {
        $hash = hash('sha256', $token);

        $cartucho = Cartucho::query()
            ->with([
                'colaborador',
                'colaborador.unidadServicio',
                'producto',
                'detalles.producto',
                'realizadoPor',
                'firmaRealizoUser',
            ])
            ->where('firma_token', $hash)
            ->whereNotNull('firma_token_expires_at')
            ->where('firma_token_expires_at', '>=', now())
            ->firstOrFail();

        // ===== Cache path (public storage) =====
        $dir  = storage_path('app/public/cartuchos/thumbs');
        if (!File::exists($dir)) File::makeDirectory($dir, 0775, true);

        $thumbName = "cartucho-{$cartucho->id}.png";
        $thumbAbs  = $dir . DIRECTORY_SEPARATOR . $thumbName;

        // Si ya existe, devuélvelo
        if (is_file($thumbAbs)) {
            return response()->file($thumbAbs, ['Content-Type' => 'image/png']);
        }

        // ===== 1) Generar PDF en temporal =====
        $tmpPdf = storage_path('app/tmp-cartucho-' . $cartucho->id . '.pdf');
        File::put($tmpPdf, Pdf::loadView('cartuchos.pdf', compact('cartucho'))
            ->setPaper('letter', 'portrait')
            ->output()
        );

        // ===== 2) Convertir página 1 a PNG =====
        try {
            $pdf = new \Spatie\PdfToImage\Pdf($tmpPdf);
            $pdf->selectPage(1)
                ->setOutputFormat('png')
                ->setResolution(160); // ajusta calidad
            $pdf->saveImage($thumbAbs);
        } finally {
            // limpiar temp
            if (is_file($tmpPdf)) @unlink($tmpPdf);
        }

        return response()->file($thumbAbs, ['Content-Type' => 'image/png']);
    }

}
