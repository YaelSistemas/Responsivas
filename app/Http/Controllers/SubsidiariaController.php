<?php

namespace App\Http\Controllers;

use App\Models\Subsidiaria;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SubsidiariaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:subsidiarias.view',   only: ['index','show']),
            new Middleware('permission:subsidiarias.create', only: ['create','store']),
            new Middleware('permission:subsidiarias.edit',   only: ['edit','update']),
            new Middleware('permission:subsidiarias.delete', only: ['destroy']),
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

        $subsidiarias = Subsidiaria::deEmpresa($this->tenantId())
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
            return view('subsidiarias.partials.table', compact('subsidiarias'))->render();
        }

        return view('subsidiarias.index', compact('subsidiarias', 'q', 'perPage'));
    }

    public function create()
    {
        return view('subsidiarias.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        Subsidiaria::create($data); // tenant/folio/created_by en el modelo

        return redirect()->route('subsidiarias.index')->with('created', true);
    }

    public function edit(Subsidiaria $subsidiaria)
    {
        return view('subsidiarias.edit', compact('subsidiaria'));
    }

    public function update(Request $request, Subsidiaria $subsidiaria)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        $subsidiaria->update($data);

        return redirect()->route('subsidiarias.index')->with('updated', true);
    }

    public function destroy(Subsidiaria $subsidiaria)
    {
        try {
            $subsidiaria->delete();
            return redirect()->route('subsidiarias.index')->with('deleted', true);
        } catch (\Throwable $e) {
            // Por si hay FKs (p.ej. colaboradores apuntando a esta subsidiaria)
            return redirect()->route('subsidiarias.index')
                ->with('error', 'No se puede eliminar: la subsidiaria está en uso.');
        }
    }
}
