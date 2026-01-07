<?php

namespace App\Http\Controllers;

use App\Models\Responsiva;
use App\Models\ResponsivaDetalle;
use App\Models\ResponsivaHistorial;
use App\Models\ProductoSerie;
use App\Models\Colaborador;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ResponsivaController extends Controller implements HasMiddleware
{
    /** ====== Spatie: permisos por acción ====== */
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            // Ver solo (lista, detalle, pdf)
            new Middleware('permission:responsivas.view',   only: ['index','show','pdf']),
            // Crear
            new Middleware('permission:responsivas.create', only: ['create','store']),
            // Editar (incluye emitirFirma y firmarEnSitio)
            new Middleware('permission:responsivas.edit',   only: ['edit','update','emitirFirma','firmarEnSitio','destroyFirma']),
            // Eliminar
            new Middleware('permission:responsivas.delete', only: ['destroy']),
        ];
    }

    /* ===================== Helpers ===================== */
    private function tenantId(): int
    {
        return (int) session('empresa_activa', auth()->user()?->empresa_id);
    }

    /** Devuelve un ID de admin “por defecto” (el primero que encuentre) */
    private function defaultAdminId(): ?int
    {
        $id = User::role('Administrador')->orderBy('id')->value('id');
        return $id ? (int) $id : null;
    }

    /* ===================== INDEX (buscador + partial) ===================== */
    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $perPage  = (int) $request->query('per_page', 50);
        $q        = trim((string) $request->query('q', ''));

        $colSel = ['id', 'nombre'];
        foreach ([
            'apellidos', 'apellido', 'apellido_paterno', 'apellido_materno',
            'primer_apellido', 'segundo_apellido',
            'area_id', 'departamento_id', 'sede_id', 'unidad_servicio_id'
        ] as $c) {
            if (Schema::hasColumn('colaboradores', $c)) $colSel[] = $c;
        }

        $with = [
            'usuario:id,name',
            'colaborador' => function ($q) use ($colSel) {
                $q->select($colSel);
            },
        ];
        if (method_exists(Colaborador::class, 'area'))         $with[] = 'colaborador.area';
        if (method_exists(Colaborador::class, 'departamento')) $with[] = 'colaborador.departamento';
        if (method_exists(Colaborador::class, 'sede'))         $with[] = 'colaborador.sede';
        if (method_exists(Colaborador::class, 'unidadServicio'))  $with[] = 'colaborador.unidadServicio';
        if (method_exists(Colaborador::class, 'unidad_servicio')) $with[] = 'colaborador.unidad_servicio';

        $rows = Responsiva::query()
            ->with($with)
            ->withCount('detalles')
            ->where('empresa_tenant_id', $tenantId)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('folio', 'like', "%{$q}%")
                      ->orWhere('fecha_entrega', 'like', "%{$q}%")
                      ->orWhereHas('colaborador', function ($c) use ($q) {
                          $c->where('nombre', 'like', "%{$q}%");
                          foreach ([
                              'apellidos', 'apellido', 'apellido_paterno', 'apellido_materno',
                              'primer_apellido', 'segundo_apellido'
                          ] as $col) {
                              if (Schema::hasColumn('colaboradores', $col)) {
                                  $c->orWhere($col, 'like', "%{$q}%");
                              }
                          }
                      })
                      ->orWhereHas('usuario', fn($u) => $u->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderBy('responsivas.folio', 'desc')   // ← orden alfabético por folio
            ->orderBy('responsivas.id', 'desc')      // ← opcional
            ->paginate($perPage)
            ->withQueryString();

        if ($request->boolean('partial')) {
            return view('responsivas.partials.table', ['responsivas' => $rows, 'rows' => $rows])->render();
        }

        return view('responsivas.index', [
            'responsivas' => $rows,
            'rows'        => $rows,
            'perPage'     => $perPage,
            'q'           => $q,
        ]);
    }

    /* ===================== CREATE ===================== */
    public function create()
    {
        $tenantId = $this->tenantId();

        // 🔹 Solo colaboradores ACTIVOS del tenant actual
        $colabQ = Colaborador::query()
            ->where('activo', 1)
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->select(['id', 'nombre', 'apellidos']);

        // 🔸 Respetar columna de empresa según estructura
        if (Schema::hasColumn('colaboradores', 'empresa_id')) {
            $colabQ->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores', 'empresa_tenant_id')) {
            $colabQ->where('empresa_tenant_id', $tenantId);
        }

        $colaboradores = $colabQ->get();

        // 🔹 Solo series disponibles de productos ACTIVOS
        $series = ProductoSerie::deEmpresa($tenantId)
            ->disponibles()
            ->whereHas('producto', fn($q) => $q->where('activo', true))
            ->with('producto:id,nombre,marca,modelo,tipo,descripcion,especificaciones,activo')
            ->orderBy('producto_id')
            ->get(['id','producto_id','serie','estado','especificaciones']);

        // 🔹 Usuarios con rol “Administrador”
        $admins = User::role('Administrador')->orderBy('name')->get(['id','name']);

        // 🔸 Preselección condicional de “Autorizó”
        $erasto = User::where('name', 'Ing. Erasto H. Enriquez Zurita')->first();
        $autorizaDefaultId = ($erasto && method_exists($erasto, 'hasRole') && $erasto->hasRole('Administrador'))
            ? $erasto->id
            : null;

        return view('responsivas.create', compact('colaboradores', 'series', 'admins', 'autorizaDefaultId'));
    }

    /* ===================== STORE ===================== */
    public function store(Request $req)
    {
        $tenantId = $this->tenantId();

        $adminIds  = User::role('Administrador')->pluck('id')->all();
        $colExists = Rule::exists('colaboradores','id');
        if (Schema::hasColumn('colaboradores','empresa_id')) {
            $colExists = $colExists->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores','empresa_tenant_id')) {
            $colExists = $colExists->where('empresa_tenant_id', $tenantId);
        }

        $req->validate([
            'motivo_entrega'        => ['required', Rule::in(['asignacion','prestamo_provisional'])],
            'colaborador_id'        => ['required', $colExists],
            'recibi_colaborador_id' => ['required', $colExists],              // ← obligatorio
            'entrego_user_id'       => ['required', Rule::in($adminIds)],     // ← obligatorio
            'autoriza_user_id'      => ['required', Rule::in($adminIds)],     // ← obligatorio
            'series_ids'            => ['required','array','min:1'],
            'series_ids.*'          => ['integer', Rule::exists('producto_series','id')->where('empresa_tenant_id', $tenantId)],
            'observaciones'         => ['nullable','string','max:2000'],
            'fecha_solicitud'       => ['required','date'],                   // ← obligatorio
            'fecha_entrega'         => ['required','date'],                   // ← obligatorio
        ]);

        $resp = null;

        DB::transaction(function() use ($req, $tenantId, &$resp) {

            $folio = $this->nextFolio($tenantId);

            $colabAsignado = Colaborador::with(['unidadServicio'])
                ->findOrFail($req->colaborador_id);

            $unidadNuevaId = $colabAsignado->unidad_servicio_id;   // <- unidad del colaborador

            $resp = Responsiva::create([
                'empresa_tenant_id'     => $tenantId,
                'folio'                 => $folio,
                'colaborador_id'        => $req->colaborador_id,
                'recibi_colaborador_id' => $req->recibi_colaborador_id,  // ← ya no se “rellena”
                'user_id'               => $req->entrego_user_id,        // ← entregó requerido
                'autoriza_user_id'      => $req->autoriza_user_id,       // ← autorizó requerido
                'motivo_entrega'        => $req->motivo_entrega,
                'fecha_solicitud'       => $req->fecha_solicitud,
                'fecha_entrega'         => $req->fecha_entrega,
                'observaciones'         => $req->observaciones,
                'sign_token'            => Str::random(64),
                'sign_token_expires_at' => (int) config('app.responsiva_sign_days', 7) > 0
                                            ? now()->addDays((int) config('app.responsiva_sign_days', 7))
                                            : null,
            ]);

            // ==========================================================
            // SNAPSHOT de productos y series al momento de la creación
            // ==========================================================
            $productosSnapshot = [];

            foreach ($req->series_ids as $serieId) {
                $serie = ProductoSerie::with('producto')->find($serieId);

                if ($serie && $serie->producto) {
                    $productosSnapshot[] = [
                        'nombre' => $serie->producto->nombre ?? '—',
                        'marca'  => $serie->producto->marca ?? '—',
                        'modelo' => $serie->producto->modelo ?? '—',
                        'serie'  => $serie->serie ?? '—',
                    ];
                }
            }

            // ==========================================================
            // 📘 Registrar historial de CREACIÓN de la responsiva
            // ==========================================================
            ResponsivaHistorial::create([
                'responsiva_id' => $resp->id,
                'user_id'       => auth()->id(),
                'accion'        => 'Creación',
                'cambios'       => [
                    'folio'                 => $resp->folio,
                    'colaborador_id'        => $resp->colaborador_id,
                    'recibi_colaborador_id' => $resp->recibi_colaborador_id,
                    'user_id'               => $resp->user_id,
                    'autoriza_user_id'      => $resp->autoriza_user_id,
                    'motivo_entrega'        => $resp->motivo_entrega,
                    'fecha_solicitud'       => $resp->fecha_solicitud,
                    'fecha_entrega'         => $resp->fecha_entrega,
                    'observaciones'         => $resp->observaciones,
                    'detalles_productos'    => $productosSnapshot,
                ],
            ]);

            // ⬇️ Traer series y verificar que su producto esté ACTIVO
            $series = ProductoSerie::deEmpresa($tenantId)
                ->whereIn('id', $req->series_ids)
                ->lockForUpdate()
                ->with('producto:id,activo')
                ->get(['id','producto_id','serie','estado','unidad_servicio_id','subsidiaria_id']);

            if ($series->count() !== count($req->series_ids)) {
                throw ValidationException::withMessages([
                    'series_ids' => 'Algunas series no existen o no pertenecen a la empresa activa.',
                ]);
            }

            $disponible = defined(ProductoSerie::class.'::ESTADO_DISPONIBLE') ? ProductoSerie::ESTADO_DISPONIBLE : 'disponible';
            $asignado   = defined(ProductoSerie::class.'::ESTADO_ASIGNADO')   ? ProductoSerie::ESTADO_ASIGNADO   : 'asignado';

            foreach ($series as $s) {
                if (!$s->producto || !$s->producto->activo) {
                    throw ValidationException::withMessages([
                        'series_ids' => "La serie {$s->serie} pertenece a un producto inactivo.",
                    ]);
                }
                if ($s->estado !== $disponible) {
                    throw ValidationException::withMessages([
                        'series_ids' => "La serie {$s->serie} ya no está disponible.",
                    ]);
                }
            }

            foreach ($series as $s) {

                // Crear detalle
                ResponsivaDetalle::create([
                    'responsiva_id'     => $resp->id,
                    'producto_id'       => $s->producto_id,
                    'producto_serie_id' => $s->id,
                ]);

                $antesUnidadId = $s->unidad_servicio_id; // 👈 unidad que tenía la serie antes

                $s->update([
                    'estado'                    => $asignado,
                    'asignado_en_responsiva_id' => $resp->id,

                    // ✅ aquí el cambio que quieres:
                    'unidad_servicio_id'        => $unidadNuevaId,

                    // ✅ opcional (si también quieres alinear subsidiaria)
                    // 'subsidiaria_id'            => $subNuevaId,
                ]);

                // 👉 Estado lógico de la asignación según motivo
                $estadoNuevo = $req->motivo_entrega === 'prestamo_provisional'
                    ? 'prestamo_provisional'
                    : 'asignado';

                // Registrar en historial
                $s->registrarHistorial([
                    'accion'          => 'asignacion',
                    'responsiva_id'   => $resp->id,
                    'estado_anterior' => $disponible,
                    'estado_nuevo'    => $estadoNuevo,   // ✅ ahora guarda el motivo
                    'cambios'         => [

                        'asignado_a' => [
                            'antes'   => null,
                            'despues' => $resp->colaborador->nombre . ' ' . $resp->colaborador->apellidos,
                        ],

                        'unidad_servicio_id' => [
                            'antes'   => $antesUnidadId,
                            'despues' => $unidadNuevaId,
                        ],

                        'entregado_por' => [
                            'antes'   => null,
                            'despues' => User::find($req->entrego_user_id)?->name,
                        ],

                        'fecha_entrega' => [
                            'antes'   => $req->fecha_entrega
                                ? \Carbon\Carbon::parse($req->fecha_entrega)->format('d-m-Y')
                                : 'SIN FECHA',
                            'despues' => $req->fecha_entrega
                                ? \Carbon\Carbon::parse($req->fecha_entrega)->format('d-m-Y')
                                : 'SIN FECHA',
                        ],

                        'subsidiaria' => [
                            'antes'   => $resp->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA',
                            'despues' => $resp->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA',
                        ],

                        // 🟢 opcional pero útil: guardar explícito el motivo
                        'motivo_entrega' => [
                            'antes'   => null,
                            'despues' => $estadoNuevo,
                        ],
                    ],
                ]);
            }
        });

        $linkFirma = $resp?->sign_token ? route('public.sign.show', $resp->sign_token) : null;

        return redirect()
            ->route('responsivas.show', $resp)
            ->with('ok', 'Responsiva creada.')
            ->with('firma_link', $linkFirma);
    }

    /* ===================== EDIT ===================== */
    public function edit(Responsiva $responsiva)
    {
        // 🔒 Verifica que la responsiva pertenezca al tenant activo
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        $tenantId = $this->tenantId();

        $responsiva->load(['detalles.producto', 'detalles.serie', 'colaborador']);

        // 🔹 Colaboradores ACTIVOS + los de la responsiva aunque estén inactivos
        $idsForzar = array_filter([
            $responsiva->colaborador_id,
            $responsiva->recibi_colaborador_id,
        ]);

        $colabQ = Colaborador::query()
            ->where(function ($q) use ($idsForzar) {
                $q->where('activo', 1);          // todos los activos
                if (!empty($idsForzar)) {
                    $q->orWhereIn('id', $idsForzar);  // + los que ya están en la responsiva
                }
            })
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->select(['id','nombre','apellidos']);

        if (Schema::hasColumn('colaboradores','empresa_id')) {
            $colabQ->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores','empresa_tenant_id')) {
            $colabQ->where('empresa_tenant_id', $tenantId);
        }

        $colaboradores = $colabQ->get();

        // ⬇️ Series disponibles SOLO de productos ACTIVOS
        $seriesDisponibles = ProductoSerie::deEmpresa($tenantId)
            ->disponibles()
            ->whereHas('producto', fn($q) => $q->where('activo', true))
            ->with('producto:id,nombre,marca,modelo,tipo,descripcion,especificaciones,activo')
            ->orderBy('producto_id')
            ->get(['id','producto_id','serie','estado']);

        $idsActuales = $responsiva->detalles->pluck('producto_serie_id')->all();

        $misSeries = ProductoSerie::deEmpresa($tenantId)
            ->whereIn('id', $idsActuales)
            ->with('producto:id,nombre,marca,modelo,tipo,descripcion,especificaciones,activo')
            ->orderBy('producto_id')
            ->get(['id','producto_id','serie','estado']);

        $series = $seriesDisponibles->concat($misSeries)->unique('id')->values();

        $admins = User::role('Administrador')->orderBy('name')->get(['id','name']);

        $selectedSeries = $misSeries->pluck('id')->all();

        return view('responsivas.edit', compact(
            'responsiva', 'colaboradores', 'series', 'admins', 'selectedSeries'
        ));
    }

    /* ===================== UPDATE ===================== */
    public function update(Request $req, Responsiva $responsiva)
    {
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        $tenantId = $this->tenantId();

        $adminIds  = User::role('Administrador')->pluck('id')->all();
        $colExists = Rule::exists('colaboradores','id');
        if (Schema::hasColumn('colaboradores','empresa_id')) {
            $colExists = $colExists->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores','empresa_tenant_id')) {
            $colExists = $colExists->where('empresa_tenant_id', $tenantId);
        }

        $req->validate([
            'motivo_entrega'        => ['required', Rule::in(['asignacion','prestamo_provisional'])],
            'colaborador_id'        => ['required', $colExists],
            'recibi_colaborador_id' => ['required', $colExists],
            'entrego_user_id'       => ['required', Rule::in($adminIds)],
            'autoriza_user_id'      => ['required', Rule::in($adminIds)],
            'series_ids'            => ['required','array','min:1'],
            'series_ids.*'          => ['integer', Rule::exists('producto_series','id')->where('empresa_tenant_id', $tenantId)],
            'fecha_solicitud'       => ['required','date'],
            'fecha_entrega'         => ['required','date'],
            'observaciones'         => ['nullable','string','max:2000'],
        ]);

        DB::transaction(function() use ($req, $responsiva, $tenantId) {

            /* ===================================
            🔹 PRIMERO OBTENEMOS LOS VALORES ORIGINALES
            =================================== */
            $original = $responsiva->getOriginal();

            $actuales = $responsiva->detalles()->pluck('producto_serie_id')->all();
            $nuevas   = $req->input('series_ids', []);

            $toAdd    = array_values(array_diff($nuevas,   $actuales));
            $toRemove = array_values(array_diff($actuales, $nuevas));

            $disponible = ProductoSerie::ESTADO_DISPONIBLE ?? 'disponible';
            $asignado   = ProductoSerie::ESTADO_ASIGNADO   ?? 'asignado';

            /* ===================================
            🔹 SERIES QUE SE AGREGAN
            =================================== */
            if ($toAdd) {

                $seriesAdd = ProductoSerie::deEmpresa($tenantId)
                    ->whereIn('id',$toAdd)
                    ->lockForUpdate()
                    ->with('producto:id,activo')
                    ->get(['id','producto_id','serie','estado']);

                // ✅ Unidad del colaborador NUEVO (del request)
                $colabNuevo = Colaborador::find($req->colaborador_id);
                $unidadNuevaId = $colabNuevo?->unidad_servicio_id;

                foreach ($seriesAdd as $s) {

                    if (!$s->producto || !$s->producto->activo) {
                        throw ValidationException::withMessages([
                            'series_ids' => "La serie {$s->serie} pertenece a un producto inactivo.",
                        ]);
                    }
                    if ($s->estado !== $disponible) {
                        throw ValidationException::withMessages([
                            'series_ids' => "La serie {$s->serie} no está disponible.",
                        ]);
                    }

                    ResponsivaDetalle::create([
                        'responsiva_id'     => $responsiva->id,
                        'producto_id'       => $s->producto_id,
                        'producto_serie_id' => $s->id,
                    ]);

                    $s->update([
                        'estado' => $asignado,
                        'asignado_en_responsiva_id' => $responsiva->id,
                        'unidad_servicio_id'        => $unidadNuevaId,
                    ]);

                    $estadoNuevo = $req->motivo_entrega === 'prestamo_provisional'
                        ? 'prestamo_provisional'
                        : 'asignado';

                    $s->registrarHistorial([
                        'accion'          => 'asignacion',
                        'responsiva_id'   => $responsiva->id,
                        'estado_anterior' => $disponible,
                        'estado_nuevo'    => $estadoNuevo,
                        'cambios'         => [

                            'asignado_a' => [
                                'antes'   => null,
                                'despues' => $responsiva->colaborador->nombre
                                            . ' ' . $responsiva->colaborador->apellidos,
                            ],

                            'entregado_por' => [
                                'antes'   => null,
                                'despues' => User::find($req->entrego_user_id)?->name,
                            ],

                            'fecha_entrega' => [
                                'antes'   => $responsiva->fecha_entrega
                                                ? \Carbon\Carbon::parse($responsiva->fecha_entrega)->format('d-m-Y')
                                                : 'SIN FECHA',
                                'despues' => $responsiva->fecha_entrega
                                                ? \Carbon\Carbon::parse($responsiva->fecha_entrega)->format('d-m-Y')
                                                : 'SIN FECHA',
                            ],

                            'subsidiaria' => [
                                'antes'   => $responsiva->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA',
                                'despues' => $responsiva->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA',
                            ],

                            'motivo_entrega' => [
                                'antes'   => null,
                                'despues' => $estadoNuevo,
                            ],
                        ]
                    ]);
                }
            }

            /* ===================================
            🔹 SERIES REMOVIDAS
            =================================== */
            if ($toRemove) {

                $seriesRem = ProductoSerie::deEmpresa($tenantId)
                    ->whereIn('id',$toRemove)
                    ->lockForUpdate()
                    ->get(['id','serie','estado','asignado_en_responsiva_id']);

                foreach ($seriesRem as $s) {

                    $s->update([
                        'estado' => $disponible,
                        'asignado_en_responsiva_id' => null,
                    ]);

                    $s->registrarHistorial([
                        'accion' => 'removido_edicion',
                        'responsiva_id'   => $responsiva->id,
                        'estado_anterior' => $original['motivo_entrega'],
                        'estado_nuevo'    => $disponible,
                        'cambios' => [

                            'removido_de' => [
                                'antes'   => $responsiva->colaborador->nombre
                                            . ' ' . $responsiva->colaborador->apellidos,
                                'despues' => null,
                            ],

                            'actualizado_por' => [
                                'antes'   => null,
                                'despues' => User::find($req->entrego_user_id)?->name,
                            ],

                            'motivo_entrega' => [
                                'antes'   => $original['motivo_entrega'],
                                'despues' => null,
                            ],

                            'fecha_entrega' => [
                                'antes' => $original['fecha_entrega']
                                            ? \Carbon\Carbon::parse($original['fecha_entrega'])->format('d-m-Y')
                                            : 'SIN FECHA',
                                'despues' => null,
                            ],

                            'subsidiaria' => [
                                'antes' => $responsiva->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA',
                                'despues' => null,
                            ],
                        ]
                    ]);
                }

                ResponsivaDetalle::where('responsiva_id', $responsiva->id)
                    ->whereIn('producto_serie_id', $toRemove)
                    ->delete();
            }

            /* ===================================
            🔹 ACTUALIZAR RESPONSIVA
            =================================== */
            $responsiva->update([
                'motivo_entrega'        => $req->motivo_entrega,
                'colaborador_id'        => $req->colaborador_id,
                'recibi_colaborador_id' => $req->recibi_colaborador_id ?: $req->colaborador_id,
                'user_id'               => $req->entrego_user_id,
                'autoriza_user_id'      => $req->autoriza_user_id,
                'fecha_solicitud'       => $req->fecha_solicitud,
                'fecha_entrega'         => $req->fecha_entrega,
                'observaciones'         => $req->observaciones,
            ]);

            /* ===================================
            🔹 CAMBIOS DE PRODUCTOS (SERIES)
            =================================== */
            $cambiosProductos = [];

            if ($toAdd) {
                foreach ($toAdd as $serieId) {
                    $serieObj = ProductoSerie::with('producto')->find($serieId);
                    if ($serieObj) {
                        $cambiosProductos[] = [
                            'serie'  => $serieObj->serie,
                            'accion' => 'Agregado',
                            'nombre' => $serieObj->producto->nombre ?? null,
                            'marca'  => $serieObj->producto->marca ?? null,
                            'modelo' => $serieObj->producto->modelo ?? null,
                        ];
                    }
                }
            }

            if ($toRemove) {
                foreach ($toRemove as $serieId) {
                    $serieObj = ProductoSerie::with('producto')->find($serieId);
                    if ($serieObj) {
                        $cambiosProductos[] = [
                            'serie'  => $serieObj->serie,
                            'accion' => 'Removido',
                            'nombre' => $serieObj->producto->nombre ?? null,
                            'marca'  => $serieObj->producto->marca ?? null,
                            'modelo' => $serieObj->producto->modelo ?? null,
                        ];
                    }
                }
            }

            // 🔄 Si quitó TODAS las que existían y puso otras → cambio total
            $removioTodas = count($toRemove) === count($actuales);
            $agregoNuevas = count($toAdd) > 0;

            if ($removioTodas && $agregoNuevas) {
                $cambiosProductos[] = [
                    'serie'  => 'Todas las series',
                    'accion' => 'Reemplazo total',
                ];
            }

            // ==========================================================
            // 📘 Historial de la RESPONSIVA (sin tocar series)
            // ==========================================================
            $cambiosResp = [];
            $camposResp = [
                'motivo_entrega',
                'colaborador_id',
                'recibi_colaborador_id',
                'user_id',
                'autoriza_user_id',
                'fecha_solicitud',
                'fecha_entrega',
                'observaciones',
            ];

            // AGREGAR SUBSIDIARIA Y UNIDAD AL HISTORIAL DE RESPONSIVA
            $antesCol   = Colaborador::find($original['colaborador_id'] ?? null);
            $despuesCol = Colaborador::find($responsiva->colaborador_id ?? null);

            $subAntes = $antesCol?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA';
            $subDesp  = $despuesCol?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA';

            $uniAntes = $antesCol?->unidadServicio?->nombre ?? 'SIN UNIDAD';
            $uniDesp  = $despuesCol?->unidadServicio?->nombre ?? 'SIN UNIDAD';

            if (($original['colaborador_id'] ?? null) != $responsiva->colaborador_id) {
                $cambiosResp['subsidiaria'] = [
                    'antes'   => $subAntes,
                    'despues' => $subDesp,
                ];

                $cambiosResp['unidad'] = [
                    'antes'   => $uniAntes,
                    'despues' => $uniDesp,
                ];
            }

            foreach ($camposResp as $campo) {
                $antes   = $original[$campo] ?? null;
                $despues = $responsiva->$campo;

                if ($antes != $despues) {
                    $cambiosResp[$campo] = [
                        'antes'   => $antes,
                        'despues' => $despues,
                    ];
                }
            }

            // 👉 AÑADIR TAMBIÉN LOS CAMBIOS DE PRODUCTOS AL HISTORIAL DE LA RESPONSIVA
            if (!empty($cambiosProductos)) {
                $cambiosResp['productos'] = $cambiosProductos;
            }

            if (!empty($cambiosResp)) {

                /* ==========================================================
                SNAPSHOT REAL DE LOS PRODUCTOS AL MOMENTO DE ESTA EDICIÓN
                ========================================================== */

                $productosSnapshot = [];

                $responsiva->load('detalles.producto','detalles.serie');

                foreach ($responsiva->detalles as $d) {
                    $productosSnapshot[] = [
                        'nombre' => $d->producto->nombre ?? '—',
                        'marca'  => $d->producto->marca ?? '—',
                        'modelo' => $d->producto->modelo ?? '—',
                        'serie'  => $d->serie->serie ?? '—',
                    ];
                }

                // 👉 AGREGAR el snapshot al historial principal de la responsiva
                $cambiosResp['detalles_productos'] = $productosSnapshot;

                \App\Models\ResponsivaHistorial::create([
                    'responsiva_id' => $responsiva->id,
                    'user_id'       => auth()->id(),
                    'accion'        => 'Actualización',
                    'cambios'       => $cambiosResp,
                ]);
            }

            // ===================================
            // ✅ SI CAMBIÓ EL COLABORADOR: actualizar unidad_servicio_id en TODAS las series actuales
            // ===================================
            if (($original['colaborador_id'] ?? null) != $responsiva->colaborador_id) {

                $colabNuevo     = Colaborador::find($responsiva->colaborador_id);
                $unidadNuevaId  = $colabNuevo?->unidad_servicio_id;

                $serieIdsActuales = $responsiva->detalles()->pluck('producto_serie_id')->all();

                $seriesActuales = ProductoSerie::deEmpresa($tenantId)
                    ->whereIn('id', $serieIdsActuales)
                    ->lockForUpdate()
                    ->get(['id','unidad_servicio_id']);

                foreach ($seriesActuales as $serie) {
                    if ((int)$serie->unidad_servicio_id !== (int)$unidadNuevaId) {
                        $serie->update([
                            'unidad_servicio_id' => $unidadNuevaId,
                        ]);
                    }
                }
            }


            /* ===================================
            🔹 DETECTAR CAMBIOS DE EDICIÓN
            =================================== */

            $camposAsignacion = [
                'colaborador_id',
                'user_id',
                'fecha_entrega',
                'motivo_entrega',
            ];

            $cambiosAsignacion = [];

            // 🔵 Reusar los mismos cambios de productos también en la parte de asignación
            if (!empty($cambiosProductos)) {
                $cambiosAsignacion['productos'] = $cambiosProductos;
            }

            $antesC = optional(\App\Models\Colaborador::find($original['colaborador_id']));
            $despuesC = optional(\App\Models\Colaborador::find($responsiva->colaborador_id));

            foreach ($camposAsignacion as $campo) {

                if ($responsiva->$campo != $original[$campo]) {

                    $antes = $original[$campo];
                    $despues = $responsiva->$campo;

                    /* ===== CAMBIO DE COLABORADOR ===== */
                    if ($campo === 'colaborador_id') {

                        $cambiosAsignacion['asignado_a'] = [
                            'antes'   => ($antesC->nombre ?? '—') . ' ' . ($antesC->apellidos ?? ''),
                            'despues' => ($despuesC->nombre ?? '—') . ' ' . ($despuesC->apellidos ?? ''),
                        ];

                        continue;
                    }

                    /* ===== CAMBIO EN ENTREGADO POR ===== */
                    if ($campo === 'user_id') {

                        $cambiosAsignacion['entregado_por'] = [
                            'antes'   => optional(\App\Models\User::find($antes))->name ?? '—',
                            'despues' => optional(\App\Models\User::find($despues))->name ?? '—',
                        ];

                        continue;
                    }

                    /* ===== CAMBIO EN FECHA ===== */
                    if ($campo === 'fecha_entrega') {

                        $cambiosAsignacion['fecha_entrega'] = [
                            'antes'   => $antes ? \Carbon\Carbon::parse($antes)->format('d-m-Y') : 'SIN FECHA',
                            'despues' => $despues ? \Carbon\Carbon::parse($despues)->format('d-m-Y') : 'SIN FECHA',
                        ];

                        continue;
                    }

                    /* ===== CAMBIO EN MOTIVO DE ENTREGA (estado) ===== */
                    if ($campo === 'motivo_entrega') {

                        $antesTxt = $antes === 'prestamo_provisional'
                            ? 'Préstamo provisional'
                            : 'Asignado';

                        $despuesTxt = $despues === 'prestamo_provisional'
                            ? 'Préstamo provisional'
                            : 'Asignado';

                        $cambiosAsignacion['estado'] = [
                            'antes'   => $antesTxt,
                            'despues' => $despuesTxt,
                        ];

                        continue;
                    }
                }
            }

            /* ===================================
            🔹 SI CAMBIÓ EL COLABORADOR → SIEMPRE mostrar Subsidiaria y Unidad
            =================================== */
            $antesSub = $antesC->subsidiaria->descripcion ?? 'SIN SUBSIDIARIA';
            $despuesSub = $despuesC->subsidiaria->descripcion ?? 'SIN SUBSIDIARIA';

            $antesUnidad = $antesC->unidadServicio->nombre ?? 'SIN UNIDAD';
            $despuesUnidad = $despuesC->unidadServicio->nombre ?? 'SIN UNIDAD';

            if ($original['colaborador_id'] != $responsiva->colaborador_id) {

                $cambiosAsignacion['subsidiaria'] = [
                    'antes'   => $antesSub,
                    'despues' => $despuesSub,
                ];

                $cambiosAsignacion['unidad'] = [
                    'antes'   => $antesUnidad,
                    'despues' => $despuesUnidad,
                ];
            }

            /* ======================================================
            🔵 FIX: FORZAR REGISTRO SI SOLO CAMBIÓ COLABORADOR
            ====================================================== */
            if (
                empty($cambiosAsignacion) &&
                $original['colaborador_id'] != $responsiva->colaborador_id
            ) {

                $cambiosAsignacion['asignado_a'] = [
                    'antes'   => ($antesC->nombre ?? '—') . ' ' . ($antesC->apellidos ?? ''),
                    'despues' => ($despuesC->nombre ?? '—') . ' ' . ($despuesC->apellidos ?? ''),
                ];

                $cambiosAsignacion['subsidiaria'] = [
                    'antes'   => $antesSub,
                    'despues' => $despuesSub,
                ];

                $cambiosAsignacion['unidad'] = [
                    'antes'   => $antesUnidad,
                    'despues' => $despuesUnidad,
                ];
            }

            /* ===================================
            🔹 SI HAY CAMBIOS, REGISTRAR HISTORIAL POR CADA SERIE
            =================================== */
            if (!empty($cambiosAsignacion)) {

                foreach ($responsiva->detalles as $det) {

                    $serie = $det->serie;

                    // ===============================================
                    // OBTENER ESTADO REAL DE ASIGNACIÓN (el primero)
                    // ===============================================
                    $historialAsignacion = $serie->historial()
                        ->where('accion', 'asignacion')
                        ->orderBy('id', 'asc')
                        ->first();

                    $estadoOriginalAsignacion = $original['motivo_entrega'];

                    // Por defecto ambos iguales
                    $estadoAnteriorSerie = $estadoOriginalAsignacion;
                    $estadoNuevoSerie    = $estadoOriginalAsignacion;

                    // ===============================================
                    // SI CAMBIÓ EL MOTIVO → actualizamos el estado
                    // ===============================================
                    if ($original['motivo_entrega'] !== $responsiva->motivo_entrega) {

                        // Estado anterior = original de asignación REAL
                        $estadoAnteriorSerie = $estadoOriginalAsignacion;

                        // Estado nuevo = motivo nuevo
                        $estadoNuevoSerie =
                            $responsiva->motivo_entrega === 'prestamo_provisional'
                                ? 'prestamo_provisional'
                                : 'asignado';
                    }

                    // ===============================================
                    // REGISTRAR HISTORIAL
                    // ===============================================
                    $serie->registrarHistorial([
                        'accion'          => 'edicion_asignacion',
                        'responsiva_id'   => $responsiva->id,
                        'estado_anterior' => $estadoAnteriorSerie,
                        'estado_nuevo'    => $estadoNuevoSerie,
                        'cambios'         => $cambiosAsignacion,
                    ]);
                }
            }
        });

        return redirect()->route('responsivas.show', $responsiva)->with('updated', 'Responsiva actualizada.');
    }

    /* ===================== SHOW ===================== */
    public function show(Responsiva $responsiva)
    {
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        $responsiva->load([
            'colaborador', 'usuario',
            'entrego', 'autoriza',
            'detalles.producto', 'detalles.serie',
        ]);

        return view('responsivas.show', compact('responsiva'));
    }

    /* ===================== FOLIO: OES-00001 por tenant ===================== */
    private function nextFolio(int $tenantId): string
    {
        $last = Responsiva::where('empresa_tenant_id', $tenantId)
            ->where('folio', 'like', 'OES-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('folio');

        $n = 1;
        if ($last && preg_match('/^OES-(\d{5,})$/', $last, $m)) {
            $n = (int)$m[1] + 1;
        }

        return 'OES-'.str_pad((string)$n, 5, '0', STR_PAD_LEFT);
    }

    /* ===================== PDF ===================== */
    public function pdf(Responsiva $responsiva)
    {
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        $pdf = Pdf::loadView('responsivas.pdf', compact('responsiva'))
                  ->setPaper('a4', 'portrait')
                  ->setOptions(['isRemoteEnabled' => true]);

        return $pdf->stream("responsiva-{$responsiva->folio}.pdf");
    }

    /* ===================== DESTROY ===================== */
    public function destroy(Responsiva $responsiva)
    {
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        // 🚫 Verificar si tiene devoluciones asociadas
        if ($responsiva->devoluciones()->exists()) {
            return redirect()
                ->route('responsivas.index')
                ->with('error', 'No se puede eliminar esta responsiva porque ya tiene una devolución asociada.');
        }

        $tenantId = $this->tenantId();

        DB::transaction(function () use ($responsiva, $tenantId) {

            $serieIds = $responsiva->detalles()->pluck('producto_serie_id')->all();

            // 👉 Guardamos el folio ANTES de eliminar
            $folioResponsiva = $responsiva->folio;

            if (!empty($serieIds)) {

                $disponible = defined(ProductoSerie::class.'::ESTADO_DISPONIBLE')
                    ? ProductoSerie::ESTADO_DISPONIBLE
                    : 'disponible';

                $asignado = defined(ProductoSerie::class.'::ESTADO_ASIGNADO')
                    ? ProductoSerie::ESTADO_ASIGNADO
                    : 'asignado';

                $series = ProductoSerie::deEmpresa($tenantId)
                    ->whereIn('id', $serieIds)
                    ->lockForUpdate()
                    ->get(['id','estado','asignado_en_responsiva_id','producto_id','serie']);

                foreach ($series as $s) {

                    // Guardamos el colaborador ANTES del borrado
                    $colab = $responsiva->colaborador
                        ? $responsiva->colaborador->nombre . ' ' . $responsiva->colaborador->apellidos
                        : 'SIN COLABORADOR';

                    $s->update([
                        'estado' => $disponible,
                        'asignado_en_responsiva_id' => null,
                    ]);

                    // Registrar en historial con datos reales
                    $s->registrarHistorial([
                        'accion' => 'liberado_eliminacion',
                        'responsiva_id' => null, // SIN FOLIO
                        'estado_anterior' => $responsiva->motivo_entrega,
                        'estado_nuevo' => $disponible,

                        'cambios' => [

                            // 🔷 Folio original de la responsiva
                            'responsiva_folio' => [
                                'antes' => $folioResponsiva,
                                'despues' => null,
                            ],

                            // 🔷 Colaborador asignado antes de la eliminación
                            'asignado_a' => [
                                'antes' => $colab,
                                'despues' => null,
                            ],

                            // 🔷 Usuario que eliminó
                            'eliminado_por' => [
                                'antes' => null,
                                'despues' => auth()->user()->name,
                            ],

                            // 🔷 Fecha de eliminación
                            'fecha' => [
                                'antes' => null,
                                'despues' => now()->format('d-m-Y H:i'),
                            ],

                            'motivo_entrega' => [
                                'antes'   => $responsiva->motivo_entrega, // "prestamo_provisional" o "asignacion"
                                'despues' => null,
                            ],

                            'fecha_entrega' => [
                                'antes' => $responsiva->fecha_entrega
                                    ? \Carbon\Carbon::parse($responsiva->fecha_entrega)->format('d-m-Y')
                                    : 'SIN FECHA',
                                'despues' => null,
                            ],

                            'subsidiaria' => [
                                'antes' => $responsiva->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA',
                                'despues' => null,
                            ],
                        ]
                    ]);
                }
            }

            // 3️⃣ Eliminar detalles y la responsiva
            $responsiva->detalles()->delete();
            $responsiva->delete();
        });

        return redirect()
            ->route('responsivas.index')
            ->with('deleted', 'Responsiva eliminada. Los equipos quedaron disponibles.');
    }

    public function emitirFirma(Responsiva $responsiva)
    {
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        if (Schema::hasColumn('responsivas', 'signed_at') && $responsiva->signed_at) {
            return redirect()
                ->route('responsivas.show', $responsiva)
                ->with('error', 'Esta responsiva ya está firmada.');
        }

        $now = now();
        $expired = $responsiva->sign_token_expires_at
            ? $now->greaterThan($responsiva->sign_token_expires_at)
            : false;

        if (!$responsiva->sign_token || $expired) {
            $responsiva->sign_token = Str::random(64);
            $days = (int) config('app.responsiva_sign_days', 7);
            $responsiva->sign_token_expires_at = $days > 0 ? $now->addDays($days) : null;
            $responsiva->save();
        }

        $linkFirma = route('public.sign.show', ['token' => $responsiva->sign_token]);

        // (Opcional) guardar url en BD como respaldo
        if (Schema::hasColumn('responsivas', 'firma_colaborador_url')) {
            $responsiva->firma_colaborador_url = $linkFirma;
            $responsiva->save();
        }

        return redirect()
            ->route('responsivas.show', $responsiva)
            ->with('ok', 'Link de firma generado.')
            ->with('firma_link', $linkFirma)
            ->with('open_firma_modal', true);   // <- clave para auto-abrir
    }

    public function firmarEnSitio(Request $req, Responsiva $responsiva)
    {
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        if (!empty($responsiva->firma_colaborador_path)) {
            return back()->with('ok', 'La responsiva ya estaba firmada.');
        }

        $req->validate([
            'firma'  => ['required'],
            'nombre' => ['nullable','string','max:255'],
        ]);

        $pngBytes = null;
        $firma = (string) $req->input('firma');
        if (Str::startsWith($firma, 'data:image')) {
            [$meta, $data] = explode(',', $firma, 2);
            $pngBytes = base64_decode($data);
        }

        if (!$pngBytes) {
            return back()->withErrors(['firma' => 'No se pudo leer la firma.']);
        }

        $dir  = 'firmas_colaboradores';
        $path = "{$dir}/responsiva-{$responsiva->id}.png";
        Storage::disk('public')->put($path, $pngBytes);

        $responsiva->firma_colaborador_path = $path;
        $responsiva->firmado_en  = now();
        $responsiva->firmado_por = $req->input('nombre') ?: ($responsiva->colaborador->nombre ?? null);
        $responsiva->firmado_ip  = $req->ip();

        $responsiva->sign_token = null;
        $responsiva->sign_token_expires_at = null;

        $responsiva->save();

        return back()->with('ok', 'Responsiva firmada en sitio.');
    }

    public function destroyFirma(Responsiva $responsiva)
    {
        abort_if($responsiva->empresa_tenant_id !== $this->tenantId(), 404);

        $path = $responsiva->firma_colaborador_path;

        if (!empty($path)) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            } else {
                foreach (['s3','local'] as $disk) {
                    try {
                        if (Storage::disk($disk)->exists($path)) {
                            Storage::disk($disk)->delete($path);
                            break;
                        }
                    } catch (\Throwable $e) { /* ignorar */ }
                }
            }
        }

        $toNull = [
            'firma_colaborador_path',
            'firma_colaborador_url',
            'firmado_en',
            'firmado_por',
            'firmado_ip',
            'sign_token',
            'sign_token_expires_at',
            'signed_at',
            'firma_colaborador_user_agent',
        ];

        foreach ($toNull as $col) {
            if (\Schema::hasColumn($responsiva->getTable(), $col)) {
                $responsiva->{$col} = null;
            }
        }

        $responsiva->save();

        return back()->with('status', 'Firma del colaborador eliminada correctamente.');
    }

    public function historial(Responsiva $responsiva)
{
    $tenant = $this->tenantId();

    if ($responsiva->empresa_tenant_id != $tenant) {
        abort(404);
    }

    $historial = ResponsivaHistorial::where('responsiva_id', $responsiva->id)
        ->with('usuario:id,name')
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function ($item) {
            $item->cambios = is_array($item->cambios)
                ? $item->cambios
                : json_decode($item->cambios, true);
            return $item;
        });
    
    $responsiva->load('series.producto');

    // ✔️ ARREGLADO: Detectar correctamente llamadas AJAX vía Fetch API
    if (request()->ajax() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
        return response()->view('responsivas.historial.modal', compact('responsiva', 'historial'));
    }

    return redirect()->route('responsivas.index');
}


}
