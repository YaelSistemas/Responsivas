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
            ->select('id','producto_id','serie','estado','asignado_en_responsiva_id')
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
        return view('productos.create', compact('tipos'));
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
            'series_lotes'   => 'nullable|string',
            'stock_inicial'  => 'nullable|integer|min:0',
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
        ]);

        $tracking = $this->trackingByTipo($data['tipo'], $data['tracking'] ?? null);

        $unidad = $tracking === 'cantidad'
            ? ($data['unidad_medida'] ?: 'pieza')
            : 'pieza';

        $specs = null;
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

        DB::transaction(function () use ($tenant, $data, $unidad, $specs, $tracking) {
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
                'descripcion'       => $data['descripcion'] ?? null,
                'especificaciones'  => $specs,
            ]);

            // tracking virtual
            $producto->tracking = $tracking;
            $producto->save();

            if ($tracking === 'serial') {
                $raw = preg_split('/\r\n|\r|\n/', (string)($data['series_lotes'] ?? ''));
                $items = collect($raw)->map(fn($s)=>trim($s))->filter()->unique()->values();

                foreach ($items as $serie) {
                    try {

                        $serieModel = ProductoSerie::create([
                            'empresa_tenant_id' => $tenant,
                            'producto_id'       => $producto->id,
                            'serie'             => $serie,
                            'estado'            => 'disponible',
                        ]);

                        // =====================================================
                        // 🟦 REGISTRO AUTOMÁTICO DE HISTORIAL DE LA SERIE
                        // =====================================================
                        if (method_exists($serieModel, 'registrarHistorial')) {

                            $serieModel->registrarHistorial([
                                'accion'          => 'creacion',
                                'estado_anterior' => null,
                                'estado_nuevo'    => 'disponible',
                                'cambios'         => [
                                    'especificaciones_base' => $producto->especificaciones ?? [],
                                    'serie' => $serieModel->serie,
                                ],
                            ]);
                        }
                        // =====================================================

                    } catch (\Throwable $e) {
                        /* duplicadas: ignorar */
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
            ? ($data['unidad_medida'] ?: 'pieza')
            : ($producto->unidad ?: 'pieza');

        $specs = null;
        if ($data['tipo'] === 'equipo_pc') {
            $specs = array_filter([
                'color' => $request->input('spec.color'),
                'ram_gb' => $request->filled('spec.ram_gb') ? (int)$request->input('spec.ram_gb') : null,
                'almacenamiento' => array_filter([
                    'tipo' => $request->input('spec.almacenamiento.tipo'),
                    'capacidad_gb' => $request->filled('spec.almacenamiento.capacidad_gb')
                        ? (int)$request->input('spec.almacenamiento.capacidad_gb') : null,
                ], fn($v)=>$v!==null && $v!==''),
                'procesador' => $request->input('spec.procesador'),
            ], fn($v)=>$v!==null && $v!=='' && $v!==[]);
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
                ['empresa_tenant_id'=>$tenant,'producto_id'=>$producto->id],
                ['cantidad'=>0]
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

        $q = trim((string) $request->query('q',''));
        $series = $producto->series()
            ->deEmpresa($this->tenantId())
            ->when($q, fn($w)=> $w->where('serie','like',"%{$q}%"))
            ->orderBy('id','desc')
            ->paginate(15)->withQueryString();

        return view('productos.series', compact('producto','series','q'));
    }

    public function seriesStore(Request $request, Producto $producto)
    {
        abort_if($producto->empresa_tenant_id !== $this->tenantId(), 404);
        if ($producto->tracking !== 'serial') abort(403);

        $data = $request->validate([
            'lotes' => 'required|string',
        ]);

        $tenant = $this->tenantId();

        $raw = preg_split('/\r\n|\r|\n/', $data['lotes']);
        $items = collect($raw)->map(fn($s)=> trim($s))->filter()->unique()->values();

        if ($items->isEmpty()) {
            return back()->with('error','No se detectaron series.');
        }

        $creadas = 0; $duplicadas = 0;
        foreach ($items as $serie) {
            try {
                ProductoSerie::create([
                    'empresa_tenant_id' => $tenant,
                    'producto_id'       => $producto->id,
                    'serie'             => $serie,
                    'estado'            => 'disponible',
                ]);
                $creadas++;
            } catch (\Throwable $e) {
                $duplicadas++;
            }
        }

        return back()->with('created', "Series creadas: {$creadas}".($duplicadas? " | Duplicadas: {$duplicadas}" : ''));
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

        // Si es equipo de cómputo => vista completa de overrides (color/ram/almacenamiento/cpu)
        if ($producto->tipo === 'equipo_pc') {
            return view('series.edit_specs', [
                'producto' => $producto,
                'serie'    => $serie,
                'over'     => (array) ($serie->especificaciones ?? []), // overrides actuales
            ]);
        }

        // Para cualquier otro tipo solo una caja de descripción
        return view('series.edit_desc', [
            'producto'    => $producto,
            'serie'       => $serie,
            'descripcion' => data_get($serie->especificaciones, 'descripcion'),
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

            // GUARDAR NUEVAS OVERRIDES
            $serie->especificaciones = $over ?: null;
            $serie->save();

            // === GUARDAR HISTORIAL ===
            $cambios = [];

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
            'descripcion' => ['nullable','string','max:2000'],
        ]);

        $oldDesc = data_get($serie->especificaciones, 'descripcion');
        $newDesc = $data['descripcion'] ?? null;

        $serie->especificaciones = $newDesc ? ['descripcion'=>$newDesc] : null;
        $serie->save();

        if($oldDesc != $newDesc){
            $serie->registrarHistorial([
                'accion' => 'edicion',
                'cambios' => [
                    'descripcion' => [
                        'antes'   => $oldDesc,
                        'despues' => $newDesc,
                    ]
                ]
            ]);
        }

        return redirect()
            ->route('productos.series', $producto)
            ->with('updated', true);
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

        $movs = $producto->movimientos()
            ->deEmpresa($this->tenantId())
            ->orderBy('id','desc')
            ->limit(20)->get();

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
