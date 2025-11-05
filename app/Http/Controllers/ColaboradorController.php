<?php

namespace App\Http\Controllers;

use App\Models\Colaborador;
use App\Models\Subsidiaria;
use App\Models\UnidadServicio;
use App\Models\Area;
use App\Models\Puesto;
use App\Models\ColaboradorHistorial; // 👈 para historial
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ColaboradorController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:colaboradores.view',   only: ['index','show']),
            new Middleware('permission:colaboradores.create', only: ['create','store']),
            new Middleware('permission:colaboradores.edit',   only: ['edit','update']),
            new Middleware('permission:colaboradores.delete', only: ['destroy']),
        ];
    }

    private function tenantId(): int
    {
        return (int) session('empresa_activa', Auth::user()->empresa_id);
    }

    public function index()
    {
        $q = request('q');
        $tenant = $this->tenantId();

        $colaboradores = Colaborador::with([
                'subsidiaria:id,nombre',
                'unidadServicio:id,nombre',
                'area:id,nombre',
                'puesto:id,nombre'
            ])
            ->where('empresa_tenant_id', $tenant)
            ->when($q, function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('nombre', 'like', "%{$q}%")
                    ->orWhere('apellidos', 'like', "%{$q}%")
                    ->orWhereHas('subsidiaria', fn($s) => $s->where('nombre','like',"%{$q}%"))
                    ->orWhereHas('area',        fn($a) => $a->where('nombre','like',"%{$q}%"))
                    ->orWhereHas('puesto',      fn($p) => $p->where('nombre','like',"%{$q}%"));
                });
            })
            ->orderByRaw("COALESCE(nombre,'') ASC")
            ->orderByRaw("COALESCE(apellidos,'') ASC")
            ->paginate((int) request('per_page', 50))
            ->withQueryString();

        if (request()->ajax() || request()->boolean('partial')) {
            return response()->view('colaboradores.partials.table', compact('colaboradores', 'q'));
        }

        $perPage = (int) request('per_page', 50);

        return view('colaboradores.index', compact('colaboradores', 'q', 'perPage'));
    }

    public function create()
    {
        $tenant = $this->tenantId();

        $subsidiarias = Subsidiaria::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');
        $unidades     = UnidadServicio::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');
        $areas        = Area::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');
        $puestos      = Puesto::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');

        return view('colaboradores.create', compact('subsidiarias','unidades','areas','puestos'));
    }

    public function store(Request $request)
    {
        $tenant = $this->tenantId();

        $data = $request->validate([
            'nombre'             => 'required|string|max:255',
            'apellidos'          => 'required|string|max:255',
            'subsidiaria_id'     => ['required', Rule::exists('subsidiarias','id')->where('empresa_tenant_id',$tenant)],
            'unidad_servicio_id' => ['nullable', Rule::exists('unidades_servicio','id')->where('empresa_tenant_id',$tenant)],
            'area_id'            => ['nullable', Rule::exists('areas','id')->where('empresa_tenant_id',$tenant)],
            'puesto_id'          => ['nullable', Rule::exists('puestos','id')->where('empresa_tenant_id',$tenant)],
        ]);

        return DB::transaction(function () use ($data, $tenant) {
            $maxFolio = Colaborador::where('empresa_tenant_id', $tenant)->lockForUpdate()->max('folio');

            $data['empresa_tenant_id'] = $tenant;
            $data['created_by']        = Auth::id();
            $data['folio']             = ($maxFolio ?? 0) + 1;

            $colaborador = Colaborador::create($data);

            return redirect()->route('colaboradores.index')->with('created', true);
        });
    }

    public function edit($id)
    {
        $tenant = $this->tenantId();

        $colaborador = Colaborador::where('empresa_tenant_id', $tenant)
            ->with(['subsidiaria','unidadServicio','area','puesto'])
            ->findOrFail($id);

        $subsidiarias = Subsidiaria::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');
        $unidades     = UnidadServicio::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');
        $areas        = Area::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');
        $puestos      = Puesto::where('empresa_tenant_id', $tenant)->orderBy('nombre')->pluck('nombre','id');

        return view('colaboradores.edit', compact('colaborador','subsidiarias','unidades','areas','puestos'));
    }

    public function update(Request $request, $id)
    {
        $tenant = $this->tenantId();

        $colaborador = Colaborador::where('empresa_tenant_id', $tenant)->findOrFail($id);
        $original = $colaborador->getOriginal();

        $data = $request->validate([
            'nombre'             => 'required|string|max:255',
            'apellidos'          => 'required|string|max:255',
            'subsidiaria_id'     => ['required', Rule::exists('subsidiarias','id')->where('empresa_tenant_id',$tenant)],
            'unidad_servicio_id' => ['nullable', Rule::exists('unidades_servicio','id')->where('empresa_tenant_id',$tenant)],
            'area_id'            => ['nullable', Rule::exists('areas','id')->where('empresa_tenant_id',$tenant)],
            'puesto_id'          => ['nullable', Rule::exists('puestos','id')->where('empresa_tenant_id',$tenant)],
        ]);

        $colaborador->update($data);

        // 🔹 Detectar y guardar cambios en historial
        $cambios = [];
        foreach ($data as $campo => $nuevoValor) {
            $anterior = $original[$campo] ?? null;
            if ($anterior != $nuevoValor) {
                $cambios[$campo] = [
                    'de' => $anterior,
                    'a'  => $nuevoValor,
                ];
            }
        }

        return redirect()->route('colaboradores.index')->with('updated', true);
    }

    public function destroy($id)
    {
        $tenant = $this->tenantId();

        $colaborador = Colaborador::where('empresa_tenant_id', $tenant)->findOrFail($id);

        // 🔹 Registrar historial antes de eliminar
        ColaboradorHistorial::create([
            'colaborador_id' => $colaborador->id,
            'user_id'        => Auth::id(), // ✅ corregido
            'accion'         => 'Eliminación',
            'cambios'        => [
                'nombre'    => $colaborador->nombre,
                'apellidos' => $colaborador->apellidos,
            ],
        ]);

        $colaborador->delete();

        return redirect()->route('colaboradores.index')->with('deleted', true);
    }

    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tenant = (int) session('empresa_activa', Auth::user()->empresa_id);

        $items = Colaborador::query()
            ->where('empresa_tenant_id', $tenant)
            ->when($q, function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('nombre', 'like', "%{$q}%")
                      ->orWhere('apellidos', 'like', "%{$q}%");
                });
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get(['id','nombre','apellidos']);

        return response()->json(
            $items->map(fn($c) => [
                'id'   => $c->id,
                'text' => trim($c->nombre.' '.$c->apellidos),
            ])
        );
    }

    public function historial($id)
{
    $tenant = $this->tenantId();
    $colaborador = Colaborador::where('empresa_tenant_id', $tenant)->findOrFail($id);

    $historiales = ColaboradorHistorial::where('colaborador_id', $colaborador->id)
        ->with('usuario:id,name')
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function ($item) {
            $item->cambios = is_array($item->cambios) ? $item->cambios : json_decode($item->cambios, true);
            return $item;
        });

    if (request()->ajax()) {
        return response()->view('colaboradores.historial.modal', compact('colaborador', 'historiales'));
    }

    return redirect()->route('colaboradores.index');
}




}
