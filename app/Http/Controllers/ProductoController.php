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

            // ✅ SOLO consumible usa descripción global en el create nuevo
            'descripcion'      => 'nullable|string|max:2000',
            'color_consumible' => ['nullable', Rule::requiredIf(fn() => $request->input('tipo') === 'consumible'),'string','max:50'],

            // ✅ Carga inicial
            'series'                   => ['required_if:tracking,serial','array','min:1'],
            'series.*.serie'           => ['required_with:series','string','max:255'],
            'series.*.subsidiaria_id'  => [
                'nullable',
                Rule::exists('subsidiarias', 'id')->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],
            'series.*.unidad_servicio_id' => [
                'nullable',
                Rule::exists('unidades_servicio', 'id')->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],

            // ✅ NUEVO: SPECS POR SERIE (PC)
            'series.*.spec_pc.color'      => ['nullable','string','max:50'],
            'series.*.spec_pc.ram_gb'     => ['nullable','integer','min:1','max:32767'],
            'series.*.spec_pc.procesador' => ['nullable','string','max:120'],

            // ✅ NUEVO (A): múltiples almacenamientos por serie
            'series.*.spec_pc.almacenamientos'                       => ['nullable','array'],
            'series.*.spec_pc.almacenamientos.*.tipo'                => ['nullable', Rule::in(['ssd','hdd','m2'])],
            'series.*.spec_pc.almacenamientos.*.capacidad_gb'        => ['nullable','integer','min:1','max:50000'],
            // Validación cruzada: si hay capacidad -> tipo requerido (por cada almacenamiento)
            'series.*.spec_pc.almacenamientos.*' => [
                'nullable',
                function ($attr, $value, $fail) {
                    if (!is_array($value)) return;

                    $cap  = $value['capacidad_gb'] ?? null;
                    $tipo = $value['tipo'] ?? null;

                    if ($cap !== null && $cap !== '' && ($tipo === null || $tipo === '')) {
                        $fail('Debes seleccionar el tipo de almacenamiento (SSD, HDD o M.2) si indicas capacidad.');
                    }
                }
            ],

            // ✅ NUEVO: SPECS POR SERIE (CELULAR)
            'series.*.spec_cel.color'             => ['nullable','string','max:255'],
            'series.*.spec_cel.almacenamiento_gb' => ['nullable','integer','min:1','max:50000'],
            'series.*.spec_cel.ram_gb'            => ['nullable','integer','min:1','max:32767'],
            'series.*.spec_cel.imei'              => ['nullable','string','max:30'],

            // ✅ NUEVO: DESCRIPCIÓN POR SERIE (impresora/monitor/pantalla/periferico/otro)
            'series.*.descripcion' => ['nullable','string','max:2000'],

            'stock_inicial_attach' => ['nullable'], // (si algún form viejo lo manda; no afecta)
            'stock_inicial'        => 'nullable|integer|min:0',

            // ✅ (LEGACY) por compatibilidad con formularios viejos
            'spec.color'                               => ['nullable','string','max:50'],
            'spec.ram_gb'                              => ['nullable','integer','min:1','max:32767'],
            'spec.almacenamiento.tipo'                 => ['nullable','in:ssd,hdd,m2'],
            'spec.almacenamiento.capacidad_gb'         => ['nullable','integer','min:1','max:50000'],
            'spec.procesador'                          => ['nullable','string','max:120'],
            'spec_cel.color'             => ['nullable','string','max:255'],
            'spec_cel.almacenamiento_gb' => ['nullable','integer','min:1','max:50000'],
            'spec_cel.ram_gb'            => ['nullable','integer','min:1','max:32767'],
            'spec_cel.imei'              => ['nullable','string','max:30'],
        ]);

        // ✅ tracking real según tipo (seguridad backend)
        $tracking = $this->trackingByTipo($data['tipo'], $data['tracking'] ?? null);

        // ✅ si no es "otro", tracking viene forzado por tipo; por eso validamos series aquí también
        if ($tracking === 'serial') {
            if (empty($data['series']) || !is_array($data['series']) || count($data['series']) < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'series' => 'Debes agregar al menos una serie.',
                ]);
            }
        }

        $unidad = $tracking === 'cantidad'
            ? ($data['unidad_medida'] ?: 'pieza')
            : 'pieza';

        // ✅ En el create nuevo:
        // - Producto base solo guarda descripción/specs cuando es consumible.
        $specs = null;
        $descripcionFinal = null;

        if ($data['tipo'] === 'consumible') {
            $specs = array_filter([
                'color' => $request->input('color_consumible'),
            ], fn($v)=>$v!==null && $v!=='');

            $descripcionFinal = $data['descripcion'] ?? null;
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
                'especificaciones'  => $specs, // solo consumible en el create nuevo
            ]);

            // tracking virtual
            $producto->tracking = $tracking;
            $producto->save();

            if ($tracking === 'serial') {

                $tipoProducto = $data['tipo'];

                // ✅ items ahora incluyen specs/observaciones por serie
                $items = collect($data['series'] ?? [])
                    ->map(function ($row) use ($tipoProducto) {

                        $serie = trim((string)($row['serie'] ?? ''));
                        if ($serie === '') return null;

                        $subs = $row['subsidiaria_id'] ?? null;
                        $subs = ($subs === '' || $subs === null) ? null : (int) $subs;

                        $unidadId = $row['unidad_servicio_id'] ?? null;
                        $unidadId = ($unidadId === '' || $unidadId === null) ? null : (int) $unidadId;

                        // ✅ specs JSON por serie
                        $serieSpecs = null;

                        if ($tipoProducto === 'equipo_pc') {
                            $pc = is_array($row['spec_pc'] ?? null) ? ($row['spec_pc'] ?? []) : [];

                            // ✅ NUEVO (A): almacenamientos[]
                            $almacenamientos = [];
                            $almRows = $pc['almacenamientos'] ?? [];
                            if (is_array($almRows)) {
                                foreach ($almRows as $a) {
                                    if (!is_array($a)) continue;

                                    $t = isset($a['tipo']) ? trim((string)$a['tipo']) : '';
                                    $c = $a['capacidad_gb'] ?? null;
                                    $c = ($c === '' || $c === null) ? null : (int) $c;

                                    // ignorar filas vacías
                                    if ($t === '' && ($c === null || $c <= 0)) continue;

                                    // si tiene capacidad, forzar que tenga tipo (ya lo valida validate, pero mantenemos defensa)
                                    if ($c !== null && $c > 0 && $t === '') continue;

                                    $almacenamientos[] = array_filter([
                                        'tipo'         => $t ?: null,
                                        'capacidad_gb' => $c,
                                    ], fn($v)=>$v!==null && $v!=='');
                                }
                            }

                            $serieSpecs = array_filter([
                                'color' => isset($pc['color']) ? trim((string)$pc['color']) : null,
                                'ram_gb' => isset($pc['ram_gb']) && $pc['ram_gb'] !== '' ? (int) $pc['ram_gb'] : null,

                                // ✅ guardamos como array de discos
                                'almacenamientos' => $almacenamientos ?: null,

                                'procesador' => isset($pc['procesador']) ? trim((string)$pc['procesador']) : null,
                            ], fn($v)=>$v!==null && $v!=='' && $v!==[]);
                        }

                        if ($tipoProducto === 'celular') {
                            $cel = is_array($row['spec_cel'] ?? null) ? ($row['spec_cel'] ?? []) : [];
                            $serieSpecs = array_filter([
                                'color' => isset($cel['color']) ? trim((string)$cel['color']) : null,
                                'almacenamiento_gb' => isset($cel['almacenamiento_gb']) && $cel['almacenamiento_gb'] !== '' ? (int) $cel['almacenamiento_gb'] : null,
                                'ram_gb' => isset($cel['ram_gb']) && $cel['ram_gb'] !== '' ? (int) $cel['ram_gb'] : null,
                                'imei' => isset($cel['imei']) ? trim((string)$cel['imei']) : null,
                            ], fn($v)=>$v!==null && $v!=='' && $v!==[]);
                        }

                        // ✅ descripción por serie (otros tipos)
                        $serieDesc = null;
                        if (in_array($tipoProducto, ['impresora','monitor','pantalla','periferico','otro'], true)) {
                            $serieDesc = trim((string)($row['descripcion'] ?? '')) ?: null;
                        }

                        return [
                            'serie'              => $serie,
                            'subsidiaria_id'     => $subs,
                            'unidad_servicio_id' => $unidadId,
                            'especificaciones'   => $serieSpecs,
                            'observaciones'      => $serieDesc,
                        ];
                    })
                    ->filter()
                    ->unique(fn($x) => $x['serie'])
                    ->values();

                // ✅ si por alguna razón quedó vacío, abortamos
                if ($items->isEmpty()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'series' => 'Debes agregar al menos una serie válida.',
                    ]);
                }

                foreach ($items as $row) {

                    // ✅ evita duplicados por tenant+producto+serie
                    $exists = ProductoSerie::where('empresa_tenant_id', $tenant)
                        ->where('producto_id', $producto->id)
                        ->where('serie', $row['serie'])
                        ->exists();

                    if ($exists) continue;

                    // ✅ seguridad extra: aunque ya validaste exists+tenant
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
                        'empresa_tenant_id'   => $tenant,
                        'producto_id'         => $producto->id,
                        'serie'               => $row['serie'],
                        'estado'              => 'disponible',
                        'subsidiaria_id'      => $row['subsidiaria_id'],
                        'unidad_servicio_id'  => $row['unidad_servicio_id'],

                        // ✅ NUEVO: guardar lo capturado en el create
                        'especificaciones'    => $row['especificaciones'], // PC/CEL
                        'observaciones'       => $row['observaciones'],    // impresora/monitor/...
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
                                'unidad_servicio_id'    => $serieModel->unidad_servicio_id,
                                'especificaciones'      => $serieModel->especificaciones,
                                'observaciones'         => $serieModel->observaciones,
                            ],
                        ]);
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

        // ✅ Solo permitimos editar estos campos desde el edit.blade.php
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'marca'  => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            // viene como "0" o "1" por el hidden + checkbox
            'activo' => ['nullable'],
        ]);

        // ✅ Normalizar activo (con hidden=0 + checkbox=1)
        $activo = $request->boolean('activo');

        // ✅ Snapshot antes
        $antes = $producto->toArray();

        // ✅ Update sin tocar tipo/tracking/unidad/sku/descripcion/especificaciones
        $producto->update([
            'nombre' => $data['nombre'],
            'marca'  => $data['marca'] ?? null,
            'modelo' => $data['modelo'] ?? null,
            'activo' => $activo ? 1 : 0,
        ]);

        // ✅ Registrar historial
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
        $tipoProducto = (string) ($producto->tipo ?? '');

        // Acepta: series[] (nuevo) o lotes (legacy)
        $data = $request->validate([
            // ✅ NUEVO: filas tipo create (BLADE NUEVO)
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

            // ✅ NUEVO: el Blade manda specs unificados: series[i][specs][...]
            'series.*.specs.color' => ['nullable', 'string', 'max:255'],
            'series.*.specs.ram_gb' => ['nullable', 'integer', 'min:1', 'max:32767'],
            'series.*.specs.procesador' => ['nullable', 'string', 'max:120'],

            'series.*.specs.almacenamiento_gb' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'series.*.specs.imei' => ['nullable', 'string', 'max:30'],

            // ✅ NUEVO (PC): múltiples almacenamientos
            'series.*.specs.almacenamientos' => ['nullable', 'array'],
            'series.*.specs.almacenamientos.*.tipo' => ['nullable', Rule::in(['SSD','HDD','M.2','ssd','hdd','m2'])],
            'series.*.specs.almacenamientos.*.capacidad_gb' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'series.*.specs.almacenamientos.*' => [
                'nullable',
                function ($attr, $value, $fail) {
                    if (!is_array($value)) return;

                    $cap  = $value['capacidad_gb'] ?? null;
                    $tipo = $value['tipo'] ?? null;

                    if ($cap !== null && $cap !== '' && ($tipo === null || $tipo === '')) {
                        $fail('Debes seleccionar el tipo de almacenamiento si indicas capacidad.');
                    }
                }
            ],

            // ✅ NUEVO: el Blade manda observaciones por serie (tipos simples)
            'series.*.observaciones' => ['nullable', 'string', 'max:2000'],

            // ✅ LEGACY: textarea
            'lotes' => ['nullable', 'string'],
        ]);

        // ===============================
        // 1) Construir items desde series[]
        // (ahora incluye especificaciones/observaciones)
        // ===============================
        $itemsFromArray = collect($data['series'] ?? [])
            ->map(function ($row) use ($tipoProducto) {
                $serie = trim((string)($row['serie'] ?? ''));
                if ($serie === '') return null;

                $subs = $row['subsidiaria_id'] ?? null;
                $subs = ($subs === '' || $subs === null) ? null : (int)$subs;

                $unidadId = $row['unidad_servicio_id'] ?? null;
                $unidadId = ($unidadId === '' || $unidadId === null) ? null : (int)$unidadId;

                $specsIn = is_array($row['specs'] ?? null) ? $row['specs'] : [];

                $serieSpecs = null;
                $serieObs   = null;

                if ($tipoProducto === 'equipo_pc') {

                    // normalizar almacenamientos (ignora filas vacías)
                    $almacenamientos = [];
                    $almRows = $specsIn['almacenamientos'] ?? [];
                    if (is_array($almRows)) {
                        foreach ($almRows as $a) {
                            if (!is_array($a)) continue;

                            $t = trim((string)($a['tipo'] ?? ''));
                            $c = $a['capacidad_gb'] ?? null;
                            $c = ($c === '' || $c === null) ? null : (int)$c;

                            if ($t === '' && ($c === null || $c <= 0)) continue;
                            if ($c !== null && $c > 0 && $t === '') continue;

                            // normaliza valores tipo a un set estable si quieres (opcional)
                            // aquí lo dejamos como venga (SSD/HDD/M.2 o ssd/hdd/m2)
                            $almacenamientos[] = array_filter([
                                'tipo'         => $t ?: null,
                                'capacidad_gb' => $c,
                            ], fn($v) => $v !== null && $v !== '');
                        }
                    }

                    $serieSpecs = array_filter([
                        'color'          => isset($specsIn['color']) ? trim((string)$specsIn['color']) : null,
                        'ram_gb'         => isset($specsIn['ram_gb']) && $specsIn['ram_gb'] !== '' ? (int)$specsIn['ram_gb'] : null,
                        'almacenamientos'=> $almacenamientos ?: null,
                        'procesador'     => isset($specsIn['procesador']) ? trim((string)$specsIn['procesador']) : null,
                    ], fn($v) => $v !== null && $v !== '' && $v !== []);

                } elseif ($tipoProducto === 'celular') {

                    $serieSpecs = array_filter([
                        'color'             => isset($specsIn['color']) ? trim((string)$specsIn['color']) : null,
                        'almacenamiento_gb' => isset($specsIn['almacenamiento_gb']) && $specsIn['almacenamiento_gb'] !== '' ? (int)$specsIn['almacenamiento_gb'] : null,
                        'ram_gb'            => isset($specsIn['ram_gb']) && $specsIn['ram_gb'] !== '' ? (int)$specsIn['ram_gb'] : null,
                        'imei'              => isset($specsIn['imei']) ? trim((string)$specsIn['imei']) : null,
                    ], fn($v) => $v !== null && $v !== '' && $v !== []);

                } else {
                    // impresora/monitor/pantalla/periferico/otro
                    $serieObs = trim((string)($row['observaciones'] ?? '')) ?: null;
                    $serieSpecs = null;
                }

                return [
                    'serie'              => $serie,
                    'subsidiaria_id'     => $subs,
                    'unidad_servicio_id' => $unidadId,
                    'especificaciones'   => $serieSpecs,
                    'observaciones'      => $serieObs,
                ];
            })
            ->filter()
            ->unique(fn($x) => $x['serie'])
            ->values();

        // ===============================
        // 2) Construir items desde lotes (si viene)
        // (lotes NO trae specs/obs)
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
                    'especificaciones'   => null,
                    'observaciones'      => null,
                ]);
        }

        // ===============================
        // 3) Unir:
        // - si la misma serie aparece en ambos:
        //   preferimos la que tenga subsidiaria_id (como antes)
        //   y además, si alguna trae especificaciones/observaciones, también se conservan.
        // ===============================
        $items = $itemsFromText
            ->concat($itemsFromArray)
            ->groupBy('serie')
            ->map(function ($group) {
                // preferimos con subsidiaria; si no, cualquiera.
                $preferred = $group->first(fn($x) => !empty($x['subsidiaria_id'])) ?: $group->first();

                // si el preferred no trae specs/obs, intenta tomarlos de otro del grupo
                if (empty($preferred['especificaciones'])) {
                    $withSpecs = $group->first(fn($x) => !empty($x['especificaciones']));
                    if ($withSpecs) $preferred['especificaciones'] = $withSpecs['especificaciones'];
                }
                if (empty($preferred['observaciones'])) {
                    $withObs = $group->first(fn($x) => !empty($x['observaciones']));
                    if ($withObs) $preferred['observaciones'] = $withObs['observaciones'];
                }

                return $preferred;
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

            $especificaciones = $row['especificaciones'] ?? null;
            $observaciones    = $row['observaciones'] ?? null;

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

                // ✅ seguridad extra: unidad pertenece al tenant (aunque ya valida)
                if (!empty($unidadId)) {
                    $ok = UnidadServicio::where('empresa_tenant_id', $tenant)
                        ->where('id', $unidadId)
                        ->exists();

                    if (!$ok) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'series' => "La serie {$serieTxt} tiene una unidad de servicio inválida o de otra empresa.",
                        ]);
                    }
                }

                $serieModel = ProductoSerie::create([
                    'empresa_tenant_id'   => $tenant,
                    'producto_id'         => $producto->id,
                    'serie'               => $serieTxt,
                    'estado'              => 'disponible',
                    'subsidiaria_id'      => $subsId,
                    'unidad_servicio_id'  => $unidadId,

                    // ✅ NUEVO: guardar lo capturado en alta masiva
                    'especificaciones'    => $especificaciones,
                    'observaciones'       => $observaciones,
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
                            'unidad_servicio_id'    => $serieModel->unidad_servicio_id,
                            'especificaciones'      => $serieModel->especificaciones,
                            'observaciones'         => $serieModel->observaciones,
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

        $subsidiarias = Subsidiaria::query()
            ->where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')
            ->get(['id','nombre']);

        $unidadesServicio = UnidadServicio::query()
            ->where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')
            ->get(['id','nombre']);

        // ✅ UNA SOLA VISTA (la unificada)
        return view('series.edit', [
            'producto'         => $producto,
            'serie'            => $serie,
            'subsidiarias'     => $subsidiarias,
            'unidadesServicio' => $unidadesServicio,
        ]);
    }

    // ===================== EDITAR UNA SERIE =====================
    public function seriesUpdate(Request $request, Producto $producto, ProductoSerie $serie)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        abort_if($serie->empresa_tenant_id !== $this->tenantId() || $serie->producto_id !== $producto->id, 404);

        $tenant = $this->tenantId();

        // snapshot (para historial)
        $antesSpecs = $serie->especificaciones ?: [];
        $antesObs   = $serie->observaciones;

        // ✅ Validación común (siempre)
        $data = $request->validate([
            'subsidiaria_id' => [
                'nullable',
                Rule::exists('subsidiarias', 'id')->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],
            'unidad_servicio_id' => [
                'nullable',
                Rule::exists('unidades_servicio', 'id')->where(fn($q) => $q->where('empresa_tenant_id', $tenant)),
            ],

            // ✅ PC (como create: spec_pc + almacenamientos[])
            'spec_pc.color' => ['nullable','string','max:50'],
            'spec_pc.ram_gb' => ['nullable','integer','min:1','max:32767'],
            'spec_pc.procesador' => ['nullable','string','max:120'],

            'spec_pc.almacenamientos' => ['nullable','array'],
            'spec_pc.almacenamientos.*.tipo' => ['nullable', Rule::in(['ssd','hdd','m2'])],
            'spec_pc.almacenamientos.*.capacidad_gb' => ['nullable','integer','min:1','max:50000'],
            'spec_pc.almacenamientos.*' => [
                'nullable',
                function ($attr, $value, $fail) {
                    if (!is_array($value)) return;
                    $cap  = $value['capacidad_gb'] ?? null;
                    $tipo = $value['tipo'] ?? null;
                    if ($cap !== null && $cap !== '' && ($tipo === null || $tipo === '')) {
                        $fail('Debes seleccionar el tipo de almacenamiento (SSD, HDD o M.2) si indicas capacidad.');
                    }
                }
            ],

            // ✅ CEL (como create: spec_cel)
            'spec_cel.color' => ['nullable','string','max:255'],
            'spec_cel.almacenamiento_gb' => ['nullable','integer','min:1','max:50000'],
            'spec_cel.ram_gb' => ['nullable','integer','min:1','max:32767'],
            'spec_cel.imei' => ['nullable','string','max:30'],

            // ✅ DESC (otros tipos) -> se guardará en observaciones
            'descripcion' => ['nullable','string','max:2000'],
        ]);

        // ✅ Set subsidiaria / unidad (directo)
        $oldSubs = $serie->subsidiaria_id;
        $oldUni  = $serie->unidad_servicio_id;

        $serie->subsidiaria_id     = $request->filled('subsidiaria_id') ? (int)$request->input('subsidiaria_id') : null;
        $serie->unidad_servicio_id = $request->filled('unidad_servicio_id') ? (int)$request->input('unidad_servicio_id') : null;

        // ✅ Construir overrides según tipo de producto
        $tipo = $producto->tipo;

        $nuevoSpecs = null;
        $nuevaObs   = $serie->observaciones; // default: conservar

        if ($tipo === 'equipo_pc') {

            // limpiar/normalizar almacenamientos (ignora filas vacías)
            $almacenamientos = [];
            $almRows = $request->input('spec_pc.almacenamientos', []);
            if (is_array($almRows)) {
                foreach ($almRows as $a) {
                    if (!is_array($a)) continue;

                    $t = isset($a['tipo']) ? trim((string)$a['tipo']) : '';
                    $c = $a['capacidad_gb'] ?? null;
                    $c = ($c === '' || $c === null) ? null : (int)$c;

                    if ($t === '' && ($c === null || $c <= 0)) continue;
                    if ($c !== null && $c > 0 && $t === '') continue;

                    $almacenamientos[] = array_filter([
                        'tipo'         => $t ?: null,
                        'capacidad_gb' => $c,
                    ], fn($v)=>$v!==null && $v!=='');
                }
            }

            $nuevoSpecs = array_filter([
                'color' => $request->input('spec_pc.color'),
                'ram_gb' => $request->filled('spec_pc.ram_gb') ? (int)$request->input('spec_pc.ram_gb') : null,
                'almacenamientos' => $almacenamientos ?: null,
                'procesador' => $request->input('spec_pc.procesador'),
            ], fn($v)=>$v!==null && $v!=='' && $v!==[]);

            // PC: observaciones por serie no aplica (puedes conservar o limpiar; yo conservo)
            // $nuevaObs = $serie->observaciones;

        } elseif ($tipo === 'celular') {

            $nuevoSpecs = array_filter([
                'color' => $request->input('spec_cel.color'),
                'almacenamiento_gb' => $request->filled('spec_cel.almacenamiento_gb') ? (int)$request->input('spec_cel.almacenamiento_gb') : null,
                'ram_gb' => $request->filled('spec_cel.ram_gb') ? (int)$request->input('spec_cel.ram_gb') : null,
                'imei' => $request->input('spec_cel.imei'),
            ], fn($v)=>$v!==null && $v!=='' && $v!==[]);

            // celular: observaciones no aplica (conservar o limpiar; yo conservo)
            // $nuevaObs = $serie->observaciones;

        } else {
            // impresora/monitor/pantalla/periferico/otro
            // ✅ descripción por serie VA EN observaciones (como en tu store)
            $desc = trim((string)($data['descripcion'] ?? ''));
            $nuevaObs = $desc !== '' ? $desc : null;

            // y ya no guardamos descripcion en JSON
            $nuevoSpecs = null;
        }

        $serie->especificaciones = $nuevoSpecs ?: null;
        $serie->observaciones    = $nuevaObs;

        $serie->save();

        // ✅ Historial (si tu modelo lo tiene)
        $cambios = [];

        if ($oldSubs != $serie->subsidiaria_id) {
            $cambios['subsidiaria_id'] = ['antes'=>$oldSubs, 'despues'=>$serie->subsidiaria_id];
        }
        if ($oldUni != $serie->unidad_servicio_id) {
            $cambios['unidad_servicio_id'] = ['antes'=>$oldUni, 'despues'=>$serie->unidad_servicio_id];
        }

        if (($antesSpecs ?: []) != ($serie->especificaciones ?: [])) {
            $cambios['especificaciones'] = ['antes'=>$antesSpecs ?: [], 'despues'=>$serie->especificaciones ?: []];
        }

        if ($antesObs != $serie->observaciones) {
            $cambios['observaciones'] = ['antes'=>$antesObs, 'despues'=>$serie->observaciones];
        }

        if (!empty($cambios) && method_exists($serie, 'registrarHistorial')) {
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
