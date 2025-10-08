<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProveedorController extends Controller
{
    /**
     * Empresa (tenant) actual desde sesión o del usuario.
     */
    protected function tenantId(): int
    {
        return (int) (session('empresa_activa') ?? Auth::user()->empresa_id);
    }

    /**
     * GET /proveedores
     * Lista con buscador, per_page y paginación AJAX (partial).
     */
    public function index(Request $request)
    {
        $tenantId = $this->tenantId();

        $q       = trim($request->query('q', ''));
        $perPage = (int) $request->query('per_page', 25);
        if ($perPage <= 0)  $perPage = 10;
        if ($perPage > 100) $perPage = 100;

        $query = Proveedor::query()
            ->where('empresa_tenant_id', $tenantId);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $like = "%{$q}%";
                $qq->where('nombre', 'like', $like)
                    ->orWhere('rfc', 'like', $like)
                   ->orWhere('calle', 'like', $like)
                   ->orWhere('colonia', 'like', $like)
                   ->orWhere('codigo_postal', 'like', $like)
                   ->orWhere('ciudad', 'like', $like)
                   ->orWhere('estado', 'like', $like);
            });
        }

        $proveedores = $query->orderBy('nombre')->paginate($perPage);

        // Respuesta parcial para AJAX (igual que en OC)
        if ($request->ajax() && $request->boolean('partial')) {
            return response()->view('proveedores.partials.table', [
                'proveedores' => $proveedores,
            ], 200);
        }

        return view('proveedores.index', [
            'proveedores' => $proveedores,
            'q'           => $q,
            'perPage'     => $perPage,
        ]);
    }

    /* =========================
     *  CRUD básico (opcional)
     * ========================= */

    public function create()
    {
        return view('proveedores.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'        => ['required', 'string', 'max:255'],
            'rfc'           => ['nullable', 'string', 'max:13'],
            'calle'         => ['nullable', 'string', 'max:255'],
            'colonia'       => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'ciudad'        => ['nullable', 'string', 'max:120'],
            'estado'        => ['nullable', 'string', 'max:120'],
        ]);

        $data['empresa_tenant_id'] = $this->tenantId();

        Proveedor::create($data);

        return redirect()->route('proveedores.index')->with('created', true);
    }

    public function edit(Proveedor $proveedore) // <- route model binding "proveedore" si usas resource
    {
        $this->authorizeCompany($proveedore);
        return view('proveedores.edit', ['proveedor' => $proveedore]);
    }

    public function update(Request $request, Proveedor $proveedore)
    {
        $this->authorizeCompany($proveedore);

        $data = $request->validate([
            'nombre'        => ['required', 'string', 'max:255'],
            'rfc'           => ['nullable', 'string', 'max:13'],
            'calle'         => ['nullable', 'string', 'max:255'],
            'colonia'       => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'ciudad'        => ['nullable', 'string', 'max:120'],
            'estado'        => ['nullable', 'string', 'max:120'],
        ]);

        $proveedore->update($data);

        return redirect()->route('proveedores.index')->with('updated', true);
    }

    public function destroy(Proveedor $proveedore)
    {
        $this->authorizeCompany($proveedore);
        $proveedore->delete();

        return redirect()->route('proveedores.index')->with('deleted', true);
    }

    /**
     * Asegura que el proveedor pertenece al tenant del usuario.
     */
    protected function authorizeCompany(Proveedor $p): void
    {
        if ((int) $p->empresa_tenant_id !== $this->tenantId()) {
            abort(403);
        }
    }
}
