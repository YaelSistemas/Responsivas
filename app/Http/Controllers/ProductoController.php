<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoExistencia;
use App\Models\ProductoSerie;
use App\Models\ProductoMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:productos.view',   only: ['index','show','existencia','series']),
            new Middleware('permission:productos.create', only: ['create','store']),
            new Middleware('permission:productos.edit',   only: ['edit','update','seriesStore','seriesEstado','existenciaAjustar']),
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
            'equipo_pc', 'impresora', 'monitor', 'pantalla' => 'serial',
            'periferico', 'consumible' => 'cantidad',
            'otro' => in_array($input, ['serial','cantidad'], true) ? $input : 'serial', // fallback
            default => 'cantidad',
        };
    }

    // ===================== LISTADO =====================
    public function index(Request $request)
{
    $tenant  = (int) session('empresa_activa', auth()->user()?->empresa_id);
    $perPage = (int) $request->query('per_page', 10);
    $q       = trim((string) $request->query('q', ''));

    $productos = \App\Models\Producto::query()
        ->where('empresa_tenant_id', $tenant)
        ->when($q, function ($w) use ($q, $tenant) {
            $w->where(function ($qq) use ($q, $tenant) {
                $qq->where('nombre', 'like', "%{$q}%")
                   ->orWhere('sku', 'like', "%{$q}%")
                   ->orWhere('marca', 'like', "%{$q}%")
                   ->orWhere('modelo', 'like', "%{$q}%")
                   ->orWhereHas('series', function ($s) use ($q, $tenant) {
                       $s->where('empresa_tenant_id', $tenant)
                         ->where('serie', 'like', "%{$q}%");
                   });
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

    // ====== Series de los productos en la página (con motivo de responsiva) ======
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
            'tipo'           => ['required', Rule::in(['equipo_pc','impresora', 'monitor','pantalla', 'periferico','consumible','otro'])],
            // tracking solo requerido si tipo = otro
            'tracking'       => ['required_if:tipo,otro', Rule::in(['serial','cantidad'])],
            'unidad_medida'  => 'nullable|string|max:30',
            'descripcion'    => 'nullable|string|max:2000',

            // Carga inicial
            'series_lotes'   => 'nullable|string',         // tracking=serial
            'stock_inicial'  => 'nullable|integer|min:0',  // tracking=cantidad

            // ===== Especificaciones (solo equipo_pc) =====
            'spec.color'                               => ['nullable','string','max:50'],
            'spec.ram_gb'                              => ['nullable','integer','min:1','max:32767'],
            'spec.almacenamiento.tipo'                 => ['nullable','in:ssd,hdd,m2'],
            'spec.almacenamiento.capacidad_gb'         => ['nullable','integer','min:1','max:50000'],
            'spec.procesador'                          => ['nullable','string','max:120'],
        ]);

        // tracking definitivo según tipo (ignoramos manipulaciones client-side)
        $tracking = $this->trackingByTipo($data['tipo'], $data['tracking'] ?? null);

        // normaliza unidad solo si es por cantidad
        $unidad = $tracking === 'cantidad'
            ? ($data['unidad_medida'] ?: 'pieza')
            : 'pieza'; // serial: nunca null

        // Especificaciones base (solo equipo_pc)
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

            // tracking virtual -> mapea en el modelo (mutator)
            $producto->tracking = $tracking;
            $producto->save();

            if ($tracking === 'serial') {
                // series iniciales
                $raw = preg_split('/\r\n|\r|\n/', (string)($data['series_lotes'] ?? ''));
                $items = collect($raw)->map(fn($s)=>trim($s))->filter()->unique()->values();

                foreach ($items as $serie) {
                    try {
                        ProductoSerie::create([
                            'empresa_tenant_id' => $tenant,
                            'producto_id'       => $producto->id,
                            'serie'             => $serie,
                            'estado'            => 'disponible',
                        ]);
                    } catch (\Throwable $e) {
                        // duplicadas: ignorar
                    }
                }
            } else {
                // existencias iniciales
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
            'tipo'           => ['required', Rule::in(['equipo_pc','impresora','monitor','pantalla','periferico','consumible','otro'])],
            // tracking solo requerido si tipo = otro
            'tracking'       => ['required_if:tipo,otro', Rule::in(['serial','cantidad'])],
            'unidad_medida'  => 'nullable|string|max:30',
            'descripcion'    => 'nullable|string|max:2000',
            'activo'         => 'sometimes|boolean',

            // ===== Especificaciones (solo equipo_pc) =====
            'spec.color'                               => ['nullable','string','max:50'],
            'spec.ram_gb'                              => ['nullable','integer','min:1','max:32767'],
            'spec.almacenamiento.tipo'                 => ['nullable','in:ssd,hdd,m2'],
            'spec.almacenamiento.capacidad_gb'         => ['nullable','integer','min:1','max:50000'],
            'spec.procesador'                          => ['nullable','string','max:120'],
        ]);

        $tracking = $this->trackingByTipo($data['tipo'], $data['tracking'] ?? null);

        $unidad = $tracking === 'cantidad'
            ? ($data['unidad_medida'] ?: 'pieza')
            : ($producto->unidad ?: 'pieza'); // serial: conserva o 'pieza'

        // Especificaciones base (solo equipo_pc) o null si cambia a otro tipo
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

        // Actualiza SOLO columnas reales
        $producto->update([
            'nombre'           => $data['nombre'],
            'sku'              => $data['sku'] ?? null,
            'marca'            => $data['marca'] ?? null,
            'modelo'           => $data['modelo'] ?? null,
            'tipo'             => $data['tipo'],
            'unidad'           => $unidad,
            'descripcion'      => $data['descripcion'] ?? null,
            'activo'           => (bool)($data['activo'] ?? $producto->activo),
            'especificaciones' => $specs,
        ]);

        // tracking virtual -> mapea a es_serializado (mutator en el modelo)
        $producto->tracking = $tracking;
        $producto->save();

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
            'lotes' => 'required|string', // una por línea
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

        $serie->update(['estado' => $data['estado']]);
        return back()->with('updated', true);
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
            'cantidad'   => 'required|integer', // en “ajuste” puede ser negativo
            'motivo'     => 'nullable|string|max:255',
            'referencia' => 'nullable|string|max:100',
        ]);

        $tenant = $this->tenantId();

        DB::transaction(function() use ($tenant,$producto,$data) {
            $stock = ProductoExistencia::firstOrCreate(
                ['empresa_tenant_id'=>$tenant,'producto_id'=>$producto->id],
                ['cantidad'=>0]
            );

            // Calcula delta
            $delta = (int) $data['cantidad'];
            if ($data['tipo']==='entrada')  { $delta =  abs($delta); }
            if ($data['tipo']==='salida')   { $delta = -abs($delta); }
            // en “ajuste” se respeta el signo

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
}
