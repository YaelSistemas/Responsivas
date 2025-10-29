<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class AreaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:areas.view',   only: ['index','show']),
            new Middleware('permission:areas.create', only: ['create','store']),
            new Middleware('permission:areas.edit',   only: ['edit','update']),
            new Middleware('permission:areas.delete', only: ['destroy']),
        ];
    }

    private function tenantId(): int
    {
        return (int) session('empresa_activa', auth()->user()->empresa_id);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        $q = trim((string) $request->query('q', ''));

        $areas = Area::deEmpresa($this->tenantId())
            ->when($q, fn($qq) => $qq->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('descripcion', 'like', "%{$q}%");
            }))
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();

        // Si usas parciales AJAX como en Unidades/Colaboradores:
        if ($request->boolean('partial')) {
            return view('areas.partials.table', compact('areas'))->render();
        }

        return view('areas.index', compact('areas', 'q', 'perPage'));
    }

    public function create()
    {
        return view('areas.create');
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenantId(); // tu método actual para identificar la empresa o tenant

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('areas')
                    ->where(fn ($query) => $query->where('empresa_tenant_id', $tenantId)),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.unique' => 'Ya existe un área o departamento con este nombre en esta empresa.',
        ]);

        $data['empresa_tenant_id'] = $tenantId;
        $data['created_by'] = Auth::id();

        Area::create($data);

        return redirect()
            ->route('areas.index')
            ->with('created', true);
    }

    public function edit(Area $area)
    {
        return view('areas.edit', compact('area'));
    }

    public function update(Request $request, Area $area)
    {
        $tenantId = $this->tenantId(); // tu método actual para identificar la empresa o tenant

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('areas')
                    ->where(fn ($query) => $query->where('empresa_tenant_id', $tenantId))
                    ->ignore($area->id), // 👈 permite usar el mismo nombre del registro actual
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.unique' => 'Ya existe un área o departamento con este nombre en esta empresa.',
        ]);

        $data['updated_by'] = Auth::id() ?? null;

        $area->update($data);

        return redirect()
            ->route('areas.index')
            ->with('updated', true);
    }

    public function destroy(Area $area)
    {
        $area->delete();
        return redirect()->route('areas.index')->with('deleted', true);
    }
}
