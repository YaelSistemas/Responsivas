<?php

namespace App\Http\Controllers;

use App\Models\UnidadServicio;
use App\Models\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UnidadServicioController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:unidades.view',   only: ['index','show']),
            new Middleware('permission:unidades.create', only: ['create','store']),
            new Middleware('permission:unidades.edit',   only: ['edit','update']),
            new Middleware('permission:unidades.delete', only: ['destroy']),
        ];
    }

    private function tenantId(): int
    {
        return (int) session('empresa_activa', Auth::user()->empresa_id);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $q       = trim((string) $request->query('q', ''));
        $tenant  = $this->tenantId();

        $unidades = UnidadServicio::where('empresa_tenant_id', $tenant)
            ->when($q, function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('nombre', 'like', "%{$q}%")
                      ->orWhere('direccion', 'like', "%{$q}%");
                });
            })
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();

        if ($request->boolean('partial')) {
            return view('unidades.partials.table', compact('unidades'))->render();
        }

        return view('unidades.index', compact('unidades', 'q', 'perPage'));
    }

    public function create()
    {
        $tenant = $this->tenantId();

        // Lista para el <select> de responsable: id => "Nombre Apellidos"
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')->orderBy('apellidos')
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => trim($c->nombre.' '.$c->apellidos)]);

        return view('unidades.create', compact('colaboradores'));
    }

    public function store(Request $request)
    {
        $tenant = $this->tenantId();

        $data = $request->validate([
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('unidades_servicio', 'nombre')
                    ->where('empresa_tenant_id', $tenant),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'responsable_id' => [
                'nullable',
                Rule::exists('colaboradores', 'id')->where('empresa_tenant_id', $tenant),
            ],
        ]);

        DB::transaction(function () use ($tenant, $data) {
            $maxFolio = UnidadServicio::where('empresa_tenant_id', $tenant)
                ->lockForUpdate()
                ->max('folio');

            UnidadServicio::create([
                'empresa_tenant_id' => $tenant,
                'created_by'        => Auth::id(),
                'folio'             => ($maxFolio ?? 0) + 1,
                'nombre'            => $data['nombre'],
                'direccion'         => $data['direccion'] ?? null,
                'responsable_id'    => $data['responsable_id'] ?? null,
            ]);
        });

        return redirect()->route('unidades.index')->with('created', true);
    }

    public function edit(UnidadServicio $unidad)
    {
        abort_if($unidad->empresa_tenant_id !== $this->tenantId(), 404);

        $tenant = $this->tenantId();
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenant)
            ->orderBy('nombre')->orderBy('apellidos')
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => trim($c->nombre.' '.$c->apellidos)]);

        return view('unidades.edit', compact('unidad', 'colaboradores'));
    }

    public function update(Request $request, UnidadServicio $unidad)
    {
        abort_if($unidad->empresa_tenant_id !== $this->tenantId(), 404);

        $tenant = $this->tenantId();

        $data = $request->validate([
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('unidades_servicio', 'nombre')
                    ->where('empresa_tenant_id', $tenant)
                    ->ignore($unidad->id),
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'responsable_id' => [
                'nullable',
                Rule::exists('colaboradores', 'id')->where('empresa_tenant_id', $tenant),
            ],
        ]);

        $unidad->update($data);

        return redirect()->route('unidades.index')->with('updated', true);
    }

    public function destroy(UnidadServicio $unidad)
    {
        abort_if($unidad->empresa_tenant_id !== $this->tenantId(), 404);

        $unidad->delete();

        return redirect()->route('unidades.index')->with('deleted', true);
    }
}
