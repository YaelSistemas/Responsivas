<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoExistencia;
use App\Models\ProductoSerie;
use App\Models\ProductoMovimiento;
use App\Models\ResponsivaDetalle; // relación con responsivas
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use App\Models\ProductoHistorial;
use App\Models\Subsidiaria;
use App\Models\UnidadServicio;

class ProductoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:productos.view',   only: ['index','show','existencia','series']),
            new Middleware('permission:productos.create', only: ['create','store']),
            new Middleware('permission:productos.edit',   only: ['edit','update','seriesStore','seriesEstado','existenciaAjustar','seriesEdit','seriesUpdate']),
            new Middleware('permission:productos.delete', only: ['destroy','seriesDestroy']),
        ];
    }

    private function tenantId(): int
    {
        return (int) session('empresa_activa', Auth::user()->empresa_id);
    }

    /** Tracking por tipo (seguridad backend) */
    private function trackingByTipo(string $tipo, ?string $input): string
    {
        return match ($tipo) {
            // Periférico ahora es por serie (como PC / impresora / monitor / pantalla)
            'equipo_pc', 'impresora', 'monitor', 'pantalla', 'periferico', 'celular' => 'serial',
            'consumible' => 'cantidad',
            'otro' => in_array($input, ['serial','cantidad'], true) ? $input : 'serial',
            default => 'cantidad',
        };
    }

    // ===================== LISTADO =====================
    public function index(Request $request)
    {
        $tenant  = (int) session('empresa_activa', auth()->user()?->empresa_id);
        $perPage = (int) $request->query('per_page', 50);
        $q       = trim((string) $request->query('q', ''));

        $productos = \App\Models\Producto::query()
            ->where('empresa_tenant_id', $tenant)
            ->when($q, function ($w) use ($q, $tenant) {
                $terms = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

                $w->where(function ($qq) use ($terms, $tenant) {
                    foreach ($terms as $term) {
                        $qq->where(function ($sub) use ($term, $tenant) {
                            $sub->where('nombre', 'like', "%{$term}%")
                                ->orWhere('marca', 'like', "%{$term}%")
                                ->orWhere('modelo', 'like', "%{$term}%")
                                ->orWhere('sku', 'like', "%{$term}%")
                                // 🔸 Nuevo: color consumible y color en JSON
                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(especificaciones, '$.color')) LIKE ?", ["%{$term}%"])
                                // 🔸 Series relacionadas
                                ->orWhereHas('series', function ($s) use ($term, $tenant) {
                                    $s->where('empresa_tenant_id', $tenant)
                                    ->where('serie', 'like', "%{$term}%");
                                });
                        });
                    }
                });
            })
            ->withCount([
                'series as series_disponibles_count' => function ($s) use ($tenant) {
                    $s->where('empresa_tenant_id', $tenant)->where('estado', 'disponible');
                },
                'series as series_total_count' => function ($s) use ($tenant) {
                    $s->where('empresa_tenant_id', $tenant);
                },
            ])
            ->with(['existencia' => function ($q2) use ($tenant) {
                $q2->where('empresa_tenant_id', $tenant);
            }])
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();

        // Series de los productos en la página
        $ids = $productos->getCollection()->pluck('id')->all();

        $seriesQuery = \App\Models\ProductoSerie::deEmpresa($tenant)
            ->whereIn('producto_id', $ids)
            ->select('id','producto_id','serie','estado','asignado_en_responsiva_id','subsidiaria_id','unidad_servicio_id')
            ->orderBy('serie');

        if (method_exists(\App\Models\ProductoSerie::class, 'responsivaAsignada')) {
            $seriesQuery->with('responsivaAsignada:id,motivo_entrega');
        }

        $seriesByProduct = $seriesQuery->get()->groupBy('producto_id');

        if ($request->boolean('partial')) {
            return view('productos.partials.table', compact('productos','seriesByProduct'))->render();
        }

        return view('productos.index', compact('productos','perPage','q','seriesByProduct'));
    }

    // ===================== CREAR =====================
    public function create()
    {
        $tenant = $this->tenantId();

        $tipos = [
            'equipo_pc'  => 'Equipo de Cómputo',
            'impresora'  => 'Impresora/Multifuncional',
            'celular'    => 'Celular/Teléfono',
            'monitor'    => 'Monitor',
            'pantalla'   => 'Pantalla/TV',
            'periferico' => 'Periférico',
            'consumible' => 'Consumible',
            'otro'       => 'Otro',
        ];

        $subsidiarias = Subsidiaria::query()
            ->where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')
            ->get(['id','nombre']);

        $unidadesServicio = UnidadServicio::query()
        ->where('empresa_tenant_id', $tenant)
        ->orderBy('nombre')
        ->get(['id','nombre']);

        return view('productos.create', compact('tipos','subsidiarias','unidadesServicio'));
    }

    public function store(Request $request)
    {
        $tenant = $this->tenantId();

        $data = $request->validate([
            'nombre'         => 'required|string|max:255',
            'sku'            => ['nullable','string','max:100', Rule::unique('productos','sku')->where('empresa_tenant_id',$tenant)],
            'marca'          => 'nullable|string|max:100',
            'modelo'         => 'nullable|string|max:100',
            'tipo'           => ['required', Rule::in(['equipo_pc','impresora','celular','monitor','pantalla','periferico','consumible','otro'])],
            'tracking'       => ['required_if:tipo,otro', Rule::in(['serial','cantidad'])],
            'unidad_medida'  => 'nullable|string|max:30',
            'descripcion'    => 'nullable|string|max:2000',
            'color_consumible' => ['nullable', Rule::requiredIf(fn() => $request->input('tipo') === 'consumible'),'string','max:50'],
            // Carga inicial
            'series'                 => ['nullable','array'],
            'series.*.serie'         => ['required_with:series','string','max:255'],
            'series.*.subsidiaria_id'=> [
                'nullable',
                Rule::exists('subsidiarias', 'id')->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],
            'series.*.unidad_servicio_id' => [
                'nullable',
                Rule::exists('unidades_servicio', 'id')
                    ->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],
            'stock_inicial'          => 'nullable|integer|min:0',
            // Especificaciones
            'spec.color'                               => ['nullable','string','max:50'],
            'spec.ram_gb'                              => ['nullable','integer','min:1','max:32767'],
            'spec.almacenamiento.tipo'                 => ['nullable','in:ssd,hdd,m2'],
            'spec.almacenamiento.capacidad_gb'         => [
                'nullable','integer','min:1','max:50000',
                function($attr,$value,$fail) use ($request) {
                    $tipo = $request->input('tipo');
                    $tipoAlm = $request->input('spec.almacenamiento.tipo');
                    if (in_array($tipo, ['equipo_pc','otro']) && $value && !$tipoAlm) {
                        $fail('Debes seleccionar el tipo de almacenamiento si indicas capacidad en GB.');
                    }
                }
            ],
            'spec.procesador'                          => ['nullable','string','max:120'],

            // Especificaciones celular
            'spec_cel.color'             => ['nullable','string','max:255'],
            'spec_cel.almacenamiento_gb' => ['nullable','integer','min:1','max:50000'],
            'spec_cel.ram_gb'            => ['nullable','integer','min:1','max:32767'],
            'spec_cel.imei'              => ['nullable','string','max:30'],
        ]);

        $tracking = $this->trackingByTipo($data['tipo'], $data['tracking'] ?? null);

        $unidad = $tracking === 'cantidad'
            ? ($data['unidad_medida'] ?: 'pieza')
            : 'pieza';

        $specs = null;

        // ✅ PC / Consumible (lo que ya tenías)
        if (in_array($data['tipo'], ['equipo_pc', 'consumible'])) {
            $specs = array_filter([
                'color' => $request->input('spec.color') ?? $request->input('color_consumible'),
                'ram_gb' => $request->filled('spec.ram_gb') ? (int)$request->input('spec.ram_gb') : null,
                'almacenamiento' => array_filter([
                    'tipo' => $request->input('spec.almacenamiento.tipo'),
                    'capacidad_gb' => $request->filled('spec.almacenamiento.capacidad_gb')
                        ? (int)$request->input('spec.almacenamiento.capacidad_gb') : null,
                ], fn($v)=>$v!==null && $v!==''),
                'procesador' => $request->input('spec.procesador'),
            ], fn($v)=>$v!==null && $v!=='' && $v!==[]);
        }

        // ✅ Celular (nuevo)
        if ($data['tipo'] === 'celular') {
            $specs = array_filter([
                'color'             => $request->input('spec_cel.color'),
                'almacenamiento_gb' => $request->filled('spec_cel.almacenamiento_gb')
                    ? (int) $request->input('spec_cel.almacenamiento_gb') : null,
                'ram_gb'            => $request->filled('spec_cel.ram_gb')
                    ? (int) $request->input('spec_cel.ram_gb') : null,
                'imei'              => $request->input('spec_cel.imei'),
            ], fn($v)=>$v!==null && $v!=='' && $v!==[]);
        }

        // ✅ Descripción FINAL (independiente del JS/textarea)
        $descripcionFinal = $data['descripcion'] ?? null;

        // si viene vacía, la tomamos del campo correcto según tipo
        if ($descripcionFinal === null || trim((string)$descripcionFinal) === '') {

            // Equipo PC: tomar el input name="spec[color]"
            if ($data['tipo'] === 'equipo_pc') {
                $descripcionFinal = $request->input('spec.color');
            }

            // Consumible: usar color_consumible
            if ($data['tipo'] === 'consumible') {
                $descripcionFinal = $request->input('color_consumible');
            }

            // Celular (opcional): si quieres que también llene descripcion
            if ($data['tipo'] === 'celular') {
                $descripcionFinal = $request->input('spec_cel.color');
            }
        }

        DB::transaction(function () use ($tenant, $data, $unidad, $specs, $tracking, $descripcionFinal) {
            $maxFolio = Producto::where('empresa_tenant_id', $tenant)->lockForUpdate()->max('folio');

            $producto = Producto::create([
                'empresa_tenant_id' => $tenant,
                'folio'             => ($maxFolio ?? 0) + 1,
                'created_by'        => Auth::id(),
                'nombre'            => $data['nombre'],
                'sku'               => $data['sku'] ?? null,
                'marca'             => $data['marca'] ?? null,
                'modelo'            => $data['modelo'] ?? null,
                'tipo'              => $data['tipo'],
                'unidad'            => $unidad,
                'activo'            => true,
                'descripcion'       => $descripcionFinal,
                'especificaciones'  => $specs,
            ]);

            // tracking virtual
            $producto->tracking = $tracking;
            $producto->save();

            if ($tracking === 'serial') {

                // ✅ Ahora viene como array: series[n][serie] + series[n][subsidiaria_id]
                $items = collect($data['series'] ?? [])
                    ->map(function ($row) {
                        $serie = trim((string)($row['serie'] ?? ''));
                        if ($serie === '') return null;

                        $subs = $row['subsidiaria_id'] ?? null;
                        $subs = ($subs === '' || $subs === null) ? null : (int) $subs;

                        $unidadId = $row['unidad_servicio_id'] ?? null;
                        $unidadId = ($unidadId === '' || $unidadId === null) ? null : (int) $unidadId;

                        return [
                            'serie'              => $serie,
                            'subsidiaria_id'     => $subs,
                            'unidad_servicio_id' => $unidadId,
                        ];
                    })
                    ->filter()
                    ->unique(fn($x) => $x['serie'])
                    ->values();

                // (opcional) si no mandaron series, no hace nada y sigue
                foreach ($items as $row) {
                    try {
                        // ✅ seguridad extra: aunque ya validaste exists+tenant, lo dejamos
                        if (!empty($row['subsidiaria_id'])) {
                            $ok = Subsidiaria::where('empresa_tenant_id', $tenant)
                                ->where('id', $row['subsidiaria_id'])
                                ->exists();

                            if (!$ok) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'series' => "La serie {$row['serie']} tiene una subsidiaria inválida o de otra empresa.",
                                ]);
                            }
                        }

                        if (!empty($row['unidad_servicio_id'])) {
                            $ok = UnidadServicio::where('empresa_tenant_id', $tenant)
                                ->where('id', $row['unidad_servicio_id'])
                                ->exists();

                            if (!$ok) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'series' => "La serie {$row['serie']} tiene una unidad de servicio inválida o de otra empresa.",
                                ]);
                            }
                        }                   

                        $serieModel = ProductoSerie::create([
                            'empresa_tenant_id' => $tenant,
                            'producto_id'       => $producto->id,
                            'serie'             => $row['serie'],
                            'estado'            => 'disponible',
                            'subsidiaria_id'    => $row['subsidiaria_id'], 
                            'unidad_servicio_id' => $row['unidad_servicio_id'],
                        ]);

                        if (method_exists($serieModel, 'registrarHistorial')) {
                            $serieModel->registrarHistorial([
                                'accion'          => 'creacion',
                                'estado_anterior' => null,
                                'estado_nuevo'    => 'disponible',
                                'cambios'         => [
                                    'especificaciones_base' => $producto->especificaciones ?? [],
                                    'serie'                 => $serieModel->serie,
                                    'subsidiaria_id'        => $serieModel->subsidiaria_id,
                                    'unidad_servicio_id' => $serieModel->unidad_servicio_id,
                                ],
                            ]);
                        }

                    } catch (\Illuminate\Validation\ValidationException $e) {
                        throw $e; // ✅ que cancele la transacción y muestre error
                    } catch (\Throwable $e) {
                        // duplicadas u otros errores: ignorar (como ya lo tenías)
                    }
                }
            } else {
                $stockInicial = (int) ($data['stock_inicial'] ?? 0);

                $stock = ProductoExistencia::firstOrCreate(
                    ['empresa_tenant_id'=>$tenant, 'producto_id'=>$producto->id],
                    ['cantidad'=>0]
                );

                if ($stockInicial > 0) {
                    $stock->update(['cantidad' => $stock->cantidad + $stockInicial]);

                    ProductoMovimiento::create([
                        'empresa_tenant_id' => $tenant,
                        'producto_id'       => $producto->id,
                        'tipo'              => 'entrada',
                        'cantidad'          => $stockInicial,
                        'motivo'            => 'Carga inicial',
                        'referencia'        => null,
                        'user_id'           => Auth::id(),
                    ]);
                }
            }
            // ✅ Registrar en historial
            ProductoHistorial::create([
                'producto_id'    => $producto->id,
                'user_id'        => Auth::id(),
                'accion'         => 'creado',
                'datos_nuevos'   => json_encode($producto->toArray(), JSON_UNESCAPED_UNICODE),
            ]);
        });

        return redirect()->route('productos.index')->with('created', true);
    }

    // ===================== MOSTRAR =====================
    public function show(Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        return view('productos.show', compact('producto'));
    }

    // ===================== EDITAR =====================
    public function edit(Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        $tipos = [
            'equipo_pc'  => 'Equipo de Cómputo',
            'impresora'  => 'Impresora/Multifuncional',
            'celular'    => 'Celular/Teléfono',
            'monitor'    => 'Monitor',
            'pantalla'   => 'Pantalla/TV',
            'periferico' => 'Periférico',
            'consumible' => 'Consumible',
            'otro'       => 'Otro',
        ];
        return view('productos.edit', compact('producto','tipos'));
    }

    public function update(Request $request, Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        $tenant = $this->tenantId();

        $data = $request->validate([
            'nombre'         => 'required|string|max:255',
            'sku'            => ['nullable','string','max:100', Rule::unique('productos','sku')->where('empresa_tenant_id',$tenant)->ignore($producto->id)],
            'marca'          => 'nullable|string|max:100',
            'modelo'         => 'nullable|string|max:100',
            'tipo'           => ['required', Rule::in(['equipo_pc','impresora','celular','monitor','pantalla','periferico','consumible','otro'])],
            'tracking'       => ['required_if:tipo,otro', Rule::in(['serial','cantidad'])],
            'unidad_medida'  => 'nullable|string|max:30',
            'descripcion'    => 'nullable|string|max:2000',
            'activo'         => 'sometimes|boolean',

            // Especificaciones
            'spec.color'                               => ['nullable','string','max:50'],
            'spec.ram_gb'                              => ['nullable','integer','min:1','max:32767'],
            'spec.almacenamiento.tipo'                 => ['nullable','in:ssd,hdd,m2'],
            'spec.almacenamiento.capacidad_gb'         => ['nullable','integer','min:1','max:50000'],
            'spec.procesador'                          => ['nullable','string','max:120'],
        ]);

        // ✅ Normalizar valor de 'activo' (checkbox)
        $data['activo'] = $request->has('activo') ? 1 : 0;

        // ✅ Guardar snapshot antes de actualizar
        $antes = $producto->toArray();

        $tracking = $this->trackingByTipo($data['tipo'], $data['tracking'] ?? null);

        $unidad = $tracking === 'cantidad'
            ? (($data['unidad_medida'] ?? null) ?: 'pieza')
            : ($producto->unidad ?: 'pieza');

        /**
         * ✅ FIX: NO borrar especificaciones si no se editaron
         * - Si tipo != equipo_pc => conservar lo que ya tiene
         * - Si tipo == equipo_pc:
         *      - si viene algún campo spec.* => recalcular y guardar
         *      - si NO viene nada => conservar
         */
        $specs = $producto->especificaciones; // por defecto conservar

        $tipo = $data['tipo'];

        $tieneAlgunaSpec = $request->hasAny([
            'spec.color',
            'spec.ram_gb',
            'spec.almacenamiento.tipo',
            'spec.almacenamiento.capacidad_gb',
            'spec.procesador',
        ]);

        if ($tipo === 'equipo_pc') {
            if ($tieneAlgunaSpec) {
                $specs = array_filter([
                    'color' => $request->input('spec.color'),
                    'ram_gb' => $request->filled('spec.ram_gb') ? (int) $request->input('spec.ram_gb') : null,
                    'almacenamiento' => array_filter([
                        'tipo' => $request->input('spec.almacenamiento.tipo'),
                        'capacidad_gb' => $request->filled('spec.almacenamiento.capacidad_gb')
                            ? (int) $request->input('spec.almacenamiento.capacidad_gb')
                            : null,
                    ], fn($v) => $v !== null && $v !== ''),
                    'procesador' => $request->input('spec.procesador'),
                ], fn($v) => $v !== null && $v !== '' && $v !== []);
            }
            // si no trae nada spec.*, se conserva $producto->especificaciones
        } else {
            // tipo != equipo_pc => conservar siempre (no tocar)
            $specs = $producto->especificaciones;
        }

        $producto->update([
            'nombre'           => $data['nombre'],
            'sku'              => $data['sku'] ?? null,
            'marca'            => $data['marca'] ?? null,
            'modelo'           => $data['modelo'] ?? null,
            'tipo'             => $data['tipo'],
            'unidad'           => $unidad,
            'descripcion'      => $data['descripcion'] ?? null,
            'activo'           => $data['activo'],
            'especificaciones' => $specs,
        ]);

        // tracking virtual
        $producto->tracking = $tracking;
        $producto->save();

        // ✅ Registrar historial solo después de guardar correctamente
        try {
            \App\Models\ProductoHistorial::create([
                'producto_id'      => $producto->id,
                'user_id'          => auth()->id(),
                'accion'           => 'actualizado',
                'datos_anteriores' => json_encode($antes, JSON_UNESCAPED_UNICODE),
                'datos_nuevos'     => json_encode($producto->fresh()->toArray(), JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            \Log::error("Error registrando historial de producto: " . $e->getMessage());
        }

        // si quedó como cantidad, aseguramos registro de existencias
        if ($producto->tracking === 'cantidad') {
            ProductoExistencia::firstOrCreate(
                ['empresa_tenant_id' => $tenant, 'producto_id' => $producto->id],
                ['cantidad' => 0]
            );
        }

        return redirect()->route('productos.index')->with('updated', true);
    }

    // ===================== ELIMINAR =====================
    public function destroy(Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);

        // Bloquea si está relacionado con alguna responsiva (en cualquier momento)
        $relacionado = ResponsivaDetalle::where('producto_id', $producto->id)->exists();
        if ($relacionado) {
            return redirect()
                ->route('productos.index')
                ->with('error', 'No se puede eliminar este producto: está relacionado con una o más responsivas.');
        }

        $producto->delete();
        return redirect()->route('productos.index')->with('deleted', true);
    }

    // ===================== SERIES =====================
    public function series(Request $request, Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);

        if ($producto->tracking !== 'serial') {
            return redirect()->route('productos.existencia', $producto);
        }

        $tenant = $this->tenantId();

        $subsidiarias = Subsidiaria::query()
            ->where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')
            ->get(['id','nombre']);

        $unidadesServicio = UnidadServicio::query()
            ->where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')
            ->get(['id','nombre']);

        $q = trim((string) $request->query('q',''));

        $series = $producto->series()
            ->deEmpresa($tenant)
            ->with(['subsidiaria:id,nombre', 'unidadServicio:id,nombre'])
            ->when($q, fn($w)=> $w->where('serie','like',"%{$q}%"))
            ->orderBy('id','desc')
            ->paginate(15)
            ->withQueryString();

        return view('productos.series', compact('producto','series','q','subsidiarias','unidadesServicio'));
    }

    public function seriesStore(Request $request, Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        if ($producto->tracking !== 'serial') abort(403);

        $tenant = $this->tenantId();

        // Acepta: series[] (nuevo) o lotes (legacy)
        $data = $request->validate([
            // ✅ NUEVO: filas tipo create
            'series' => ['nullable', 'array'],
            'series.*.serie' => ['required_with:series', 'string', 'max:255'],
            'series.*.subsidiaria_id' => [
                'nullable',
                Rule::exists('subsidiarias', 'id')
                    ->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],
            'series.*.unidad_servicio_id' => [
                'nullable',
                Rule::exists('unidades_servicio', 'id')
                    ->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],

            // ✅ LEGACY: textarea
            'lotes' => ['nullable', 'string'],
        ]);

        // ===============================
        // 1) Construir items desde series[]
        // ===============================
        $itemsFromArray = collect($data['series'] ?? [])
            ->map(function ($row) {
                $serie = trim((string)($row['serie'] ?? ''));
                if ($serie === '') return null;

                $subs = $row['subsidiaria_id'] ?? null;
                $subs = ($subs === '' || $subs === null) ? null : (int)$subs;

                $unidadId = $row['unidad_servicio_id'] ?? null;
                $unidadId = ($unidadId === '' || $unidadId === null) ? null : (int)$unidadId;

                return [
                    'serie'              => $serie,
                    'subsidiaria_id'     => $subs,
                    'unidad_servicio_id' => $unidadId,
                ];

            })
            ->filter()
            // unique por serie
            ->unique(fn($x) => $x['serie'])
            ->values();

        // ===============================
        // 2) Construir items desde lotes (si viene)
        // ===============================
        $itemsFromText = collect();

        if (!empty($data['lotes'])) {
            $raw = preg_split('/\r\n|\r|\n/', (string)$data['lotes']);
            $itemsFromText = collect($raw)
                ->map(fn($s) => trim((string)$s))
                ->filter()
                ->unique()
                ->values()
                ->map(fn($serie) => [
                    'serie'              => $serie,
                    'subsidiaria_id'     => null,
                    'unidad_servicio_id' => null,
                ]);
        }

        // ===============================
        // 3) Unir (prioridad: series[] manda subsidiaria; lotes solo serie)
        // Si misma serie aparece en ambos, se queda la que tenga subsidiaria (series[])
        // ===============================
        $items = $itemsFromText
            ->concat($itemsFromArray)
            ->groupBy('serie')
            ->map(function ($group) {
                // si alguna fila trae subsidiaria_id, la preferimos
                $withSubs = $group->first(fn($x) => !empty($x['subsidiaria_id']));
                return $withSubs ?: $group->first();
            })
            ->values();

        if ($items->isEmpty()) {
            return back()->with('error', 'No se detectaron series.');
        }

        $creadas = 0;
        $duplicadas = 0;

        foreach ($items as $row) {
            $serieTxt = $row['serie'];
            $subsId   = $row['subsidiaria_id'] ?? null;
            $unidadId = $row['unidad_servicio_id'] ?? null;

            try {
                // ✅ evita duplicados por producto + serie + tenant
                $exists = ProductoSerie::where('empresa_tenant_id', $tenant)
                    ->where('producto_id', $producto->id)
                    ->where('serie', $serieTxt)
                    ->exists();

                if ($exists) {
                    $duplicadas++;
                    continue;
                }

                // ✅ seguridad extra: subsidiaria pertenece al tenant (aunque ya valida)
                if (!empty($subsId)) {
                    $ok = Subsidiaria::where('empresa_tenant_id', $tenant)
                        ->where('id', $subsId)
                        ->exists();

                    if (!$ok) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'series' => "La serie {$serieTxt} tiene una subsidiaria inválida o de otra empresa.",
                        ]);
                    }
                }

                $serieModel = ProductoSerie::create([
                    'empresa_tenant_id' => $tenant,
                    'producto_id'       => $producto->id,
                    'serie'             => $serieTxt,
                    'estado'            => 'disponible',
                    'subsidiaria_id'    => $subsId, 
                    'unidad_servicio_id' => $unidadId,
                ]);

                $creadas++;

                // ✅ historial
                if (method_exists($serieModel, 'registrarHistorial')) {
                    $serieModel->registrarHistorial([
                        'accion'          => 'creacion',
                        'estado_anterior' => null,
                        'estado_nuevo'    => 'disponible',
                        'cambios'         => [
                            'serie'                 => $serieModel->serie,
                            'subsidiaria_id'        => $serieModel->subsidiaria_id,
                            'especificaciones_base' => $producto->especificaciones ?? [],
                        ],
                    ]);
                }

            } catch (\Illuminate\Validation\ValidationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                // Si algo raro pasa, lo contamos como duplicada/omitida
                $duplicadas++;
            }
        }

        return back()->with(
            'created',
            "Series creadas: {$creadas}" . ($duplicadas ? " | Duplicadas/Omitidas: {$duplicadas}" : '')
        );
    }

    public function seriesDestroy(Producto $producto, ProductoSerie $serie)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        abort_if($serie->empresa_tenant_id !== $this->tenantId() || $serie->producto_id !== $producto->id, 404);

        // ⛔ No permitir borrar si la serie está/estuvo en alguna responsiva
        $tieneHistorial = ResponsivaDetalle::where('producto_serie_id', $serie->id)->exists();
        if ($tieneHistorial) {
            return back()->with('error','No puedes eliminar esta serie: ya está relacionada con una responsiva.');
        }

        if ($serie->estado !== 'disponible') {
            return back()->with('error','Solo puedes eliminar series en estado "disponible".');
        }

        $serie->delete();
        return back()->with('deleted', true);
    }

    public function seriesEstado(Request $request, Producto $producto, ProductoSerie $serie)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        abort_if($serie->empresa_tenant_id !== $this->tenantId() || $serie->producto_id !== $producto->id, 404);

        $data = $request->validate([
            'estado' => 'required|in:disponible,asignado,devuelto,baja,reparacion',
        ]);

        // Solo tocamos 'estado'. No limpiamos 'asignado_en_responsiva_id' para conservar el rastro histórico.
        $serie->update(['estado' => $data['estado']]);

        return back()->with('updated', true);
    }

    // ===================== EDITAR UNA SERIE (vista condicional) =====================
    public function seriesEdit(Producto $producto, ProductoSerie $serie)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        abort_if($serie->empresa_tenant_id !== $this->tenantId() || $serie->producto_id !== $producto->id, 404);

        $tenant = $this->tenantId();

        $subsidiarias = \App\Models\Subsidiaria::query()
            ->where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')
            ->get(['id','nombre']);

        $unidadesServicio = \App\Models\UnidadServicio::query()
            ->where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')
            ->get(['id','nombre']);

        // Si es equipo de cómputo => vista completa
        if ($producto->tipo === 'equipo_pc') {
            return view('series.edit_specs', [
                'producto'     => $producto,
                'serie'        => $serie,
                'over'         => (array) ($serie->especificaciones ?? []),
                'subsidiarias' => $subsidiarias, 
                'unidadesServicio' => $unidadesServicio,
            ]);
        }

        // Otros tipos => solo descripción
        return view('series.edit_desc', [
            'producto'     => $producto,
            'serie'        => $serie,
            'descripcion'  => data_get($serie->especificaciones, 'descripcion'),
            'subsidiarias' => $subsidiarias, 
            'unidadesServicio' => $unidadesServicio,
        ]);
    }

    // ===================== EDITAR UNA SERIE =====================
    public function seriesUpdate(Request $request, Producto $producto, ProductoSerie $serie)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        abort_if($serie->empresa_tenant_id !== $this->tenantId() || $serie->producto_id !== $producto->id, 404);

        // === GUARDAR SNAPSHOT ANTES DEL CAMBIO ===
        $antes = $serie->especificaciones ? $serie->especificaciones : [];

        // Equipo de cómputo: specs completas
        if ($producto->tipo === 'equipo_pc') {
            $data = $request->validate([
                'spec.color'                       => ['nullable','string','max:50'],
                'spec.ram_gb'                      => ['nullable','integer','min:1','max:32767'],
                'spec.almacenamiento.tipo'         => ['nullable','in:ssd,hdd,m2'],
                'spec.almacenamiento.capacidad_gb' => ['nullable','integer','min:1','max:50000'],
                'spec.procesador'                  => ['nullable','string','max:120'],
                'subsidiaria_id' => ['nullable', Rule::exists('subsidiarias', 'id') ->where(fn($q) => $q->where('empresa_tenant_id', $this->tenantId())),],
                'unidad_servicio_id' => ['nullable', Rule::exists('unidades_servicio', 'id') ->where(fn($q) => $q->where('empresa_tenant_id', $this->tenantId())),],
            ]);

            // overrides nuevos
            $over = array_filter([
                'color' => $request->input('spec.color'),
                'ram_gb' => $request->filled('spec.ram_gb') ? (int)$request->input('spec.ram_gb') : null,
                'almacenamiento' => array_filter([
                    'tipo'          => $request->input('spec.almacenamiento.tipo'),
                    'capacidad_gb'  => $request->filled('spec.almacenamiento.capacidad_gb')
                                            ? (int)$request->input('spec.almacenamiento.capacidad_gb')
                                            : null,
                ], fn($v)=>$v!==null),
                'procesador' => $request->input('spec.procesador'),
            ], fn($v)=>$v!==null);

            $tenant = $this->tenantId();
            $subsidiariaId = $request->filled('subsidiaria_id')
                ? (int) $request->input('subsidiaria_id')
                : null;

            if ($subsidiariaId) {
                $ok = Subsidiaria::where('empresa_tenant_id', $tenant)
                    ->where('id', $subsidiariaId)
                    ->exists();

                if (!$ok) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'subsidiaria_id' => 'Subsidiaria inválida o no pertenece a la empresa activa.',
                    ]);
                }
            }

            $oldSubs = $serie->subsidiaria_id;
            $serie->subsidiaria_id = $subsidiariaId;

            $unidadId = $request->filled('unidad_servicio_id')
                ? (int) $request->input('unidad_servicio_id')
                : null;

            if ($unidadId) {
                $ok = UnidadServicio::where('empresa_tenant_id', $tenant)
                    ->where('id', $unidadId)
                    ->exists();

                if (!$ok) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'unidad_servicio_id' => 'Unidad de servicio inválida o no pertenece a la empresa activa.',
                    ]);
                }
            }

            $oldUnidad = $serie->unidad_servicio_id;
            $serie->unidad_servicio_id = $unidadId;

            // GUARDAR NUEVAS OVERRIDES
            $serie->especificaciones = $over ?: null;
            $serie->save();

            // === GUARDAR HISTORIAL ===
            $cambios = [];

            // ✅ subsidiaria también en historial
            if ($oldSubs != $subsidiariaId) {
                $cambios['subsidiaria_id'] = [
                    'antes'   => $oldSubs,
                    'despues' => $subsidiariaId,
                ];
            }

            if ($oldUnidad != $unidadId) {
                $cambios['unidad_servicio_id'] = [
                    'antes'   => $oldUnidad,
                    'despues' => $unidadId,
                ];
            }

            foreach(['color','ram_gb','procesador'] as $campo){
                $old = data_get($antes, $campo);
                $new = data_get($over, $campo);

                if($old != $new){
                    $cambios[$campo] = [
                        'antes'   => $old,
                        'despues' => $new,
                    ];
                }
            }

            // almacenamiento (tipo y capacidad)
            $oldAlm = data_get($antes,'almacenamiento',[]);
            $newAlm = data_get($over,'almacenamiento',[]);
            if($oldAlm != $newAlm){
                $cambios['almacenamiento'] = [
                    'antes'   => $oldAlm,
                    'despues' => $newAlm,
                ];
            }

            if(!empty($cambios)){
                $serie->registrarHistorial([
                    'accion' => 'edicion',
                    'cambios' => $cambios,
                ]);
            }

            return redirect()
                ->route('productos.series', $producto)
                ->with('updated', 'Serie actualizada.');
        }

        // === OTROS TIPOS (SOLO DESCRIPCIÓN) ===
        $data = $request->validate([
            'descripcion'    => ['nullable','string','max:2000'],
            'subsidiaria_id' => [
                'nullable',
                Rule::exists('subsidiarias', 'id')
                    ->where(fn($q) => $q->where('empresa_tenant_id', $this->tenantId())),
            ],
            'unidad_servicio_id' => [
                'nullable',
                Rule::exists('unidades_servicio', 'id')
                    ->where(fn($q) => $q->where('empresa_tenant_id', $this->tenantId())),
            ],
        ]);

        $oldDesc = data_get($serie->especificaciones, 'descripcion');
        $newDesc = $data['descripcion'] ?? null;

        $oldSubs = $serie->subsidiaria_id;
        $newSubs = $data['subsidiaria_id'] ?? null;

        // ✅ guardar subsidiaria
        $serie->subsidiaria_id = $newSubs;

        $oldUnidad = $serie->unidad_servicio_id;
        $newUnidad = $data['unidad_servicio_id'] ?? null;

        $serie->unidad_servicio_id = $newUnidad;

        // ✅ guardar descripción
        $serie->especificaciones = $newDesc ? ['descripcion' => $newDesc] : null;

        $serie->save();

        // ✅ historial (una sola vez)
        $cambios = [];

        if ($oldDesc != $newDesc) {
            $cambios['descripcion'] = [
                'antes'   => $oldDesc,
                'despues' => $newDesc,
            ];
        }

        if ($oldSubs != $newSubs) {
            $cambios['subsidiaria_id'] = [
                'antes'   => $oldSubs,
                'despues' => $newSubs,
            ];
        }

        if ($oldUnidad != $newUnidad) {
            $cambios['unidad_servicio_id'] = [
                'antes'   => $oldUnidad,
                'despues' => $newUnidad,
            ];
        }

        if (!empty($cambios)) {
            $serie->registrarHistorial([
                'accion'  => 'edicion',
                'cambios' => $cambios,
            ]);
        }

        return redirect()
            ->route('productos.series', $producto)
            ->with('updated', 'Serie actualizada.');

    }

    // ===================== EXISTENCIA (NO SERIAL) =====================
    public function existencia(Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);

        if ($producto->tracking !== 'cantidad') {
            return redirect()->route('productos.series', $producto);
        }

        $stock = ProductoExistencia::firstOrCreate(
            ['empresa_tenant_id'=>$this->tenantId(),'producto_id'=>$producto->id],
            ['cantidad'=>0]
        );

        $tenant = $this->tenantId();

        $movManual = DB::table('producto_movimientos')
            ->where('empresa_tenant_id', $tenant)
            ->where('producto_id', $producto->id)
            ->selectRaw("
                created_at as fecha,
                tipo,
                cantidad,
                motivo,
                referencia
            ");

        $movCartuchos = DB::table('cartucho_detalles as cd')
            ->join('cartuchos as c', 'c.id', '=', 'cd.cartucho_id')
            ->where('c.empresa_tenant_id', $tenant)
            ->where('cd.producto_id', $producto->id)
            ->groupBy('c.id', 'c.folio', 'c.fecha_solicitud')
            ->selectRaw("
                c.fecha_solicitud as fecha,
                'salida' as tipo,
                SUM(cd.cantidad) as cantidad,
                'Entrega de cartuchos' as motivo,
                c.folio as referencia
            ");

        $movs = DB::query()
            ->fromSub($movManual->unionAll($movCartuchos), 'movs')
            ->orderByDesc('fecha')
            ->limit(20)
            ->get();

        return view('productos.existencia', compact('producto','stock','movs'));
    }

    public function existenciaAjustar(Request $request, Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        if ($producto->tracking !== 'cantidad') abort(403);

        $data = $request->validate([
            'tipo'       => 'required|in:entrada,salida,ajuste',
            'cantidad'   => 'required|integer',
            'motivo'     => 'nullable|string|max:255',
            'referencia' => 'nullable|string|max:100',
        ]);

        $tenant = $this->tenantId();

        DB::transaction(function() use ($tenant,$producto,$data) {
            $stock = ProductoExistencia::firstOrCreate(
                ['empresa_tenant_id'=>$tenant,'producto_id'=>$producto->id],
                ['cantidad'=>0]
            );

            $delta = (int) $data['cantidad'];
            if ($data['tipo']==='entrada')  { $delta =  abs($delta); }
            if ($data['tipo']==='salida')   { $delta = -abs($delta); }

            $nuevo = $stock->cantidad + $delta;
            if ($nuevo < 0) {
                throw new \RuntimeException('No hay stock suficiente.');
            }

            $stock->update(['cantidad'=>$nuevo]);

            ProductoMovimiento::create([
                'empresa_tenant_id'=>$tenant,
                'producto_id'      =>$producto->id,
                'tipo'             =>$data['tipo'],
                'cantidad'         =>$delta,
                'motivo'           =>$data['motivo'] ?? null,
                'referencia'       =>$data['referencia'] ?? null,
                'user_id'          => Auth::id(),
            ]);
        });

        return back()->with('updated', true);
    }

    public function historial($id)
    {
        \Log::debug("Entró al historial del producto", ['id' => $id]);

        $producto = Producto::with('historial.user')->find($id);

        if (!$producto) {
            \Log::debug("Producto no encontrado", ['id' => $id]);
            return response()->json(['error' => 'No encontrado'], 404);
        }

        $historial = $producto->historial()->latest()->get();

        \Log::debug("Historial count", ['total' => $historial->count()]);

        // Si el request es AJAX o no, devuelve la vista igual
        return response()->view('productos.historial.modal', compact('producto', 'historial'));
    }

}
