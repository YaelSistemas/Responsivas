<?php

namespace App\Http\Controllers;

use App\Models\Puesto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PuestoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:puestos.view',   only: ['index','show']),
            new Middleware('permission:puestos.create', only: ['create','store']),
            new Middleware('permission:puestos.edit',   only: ['edit','update']),
            new Middleware('permission:puestos.delete', only: ['destroy']),
        ];
    }

    private function tenantId(): int
    {
        return (int) session('empresa_activa', auth()->user()->empresa_id);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $q       = trim((string) $request->query('q', ''));

        $puestos = Puesto::deEmpresa($this->tenantId())
            ->when($q, fn($qq) =>
                $qq->where(function($w) use ($q){
                    $w->where('nombre', 'like', "%{$q}%")
                      ->orWhere('descripcion', 'like', "%{$q}%");
                })
            )
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();

        if ($request->boolean('partial')) {
            return view('puestos.partials.table', compact('puestos'))->render();
        }

        return view('puestos.index', compact('puestos', 'q', 'perPage'));
    }

    public function create()
    {
        return view('puestos.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        Puesto::create($data); // tenant/folio/created_by se ponen en el modelo

        return redirect()->route('puestos.index')->with('created', true);
    }

    public function edit(Puesto $puesto)
    {
        return view('puestos.edit', compact('puesto'));
    }

    public function update(Request $request, Puesto $puesto)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        $puesto->update($data); // no tocamos tenant ni folio

        return redirect()->route('puestos.index')->with('updated', true);
    }

    public function destroy(Puesto $puesto)
    {
        $puesto->delete();

        return redirect()->route('puestos.index')->with('deleted', true);
    }
}
