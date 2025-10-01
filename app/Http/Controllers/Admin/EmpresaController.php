<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function index(Request $request)
{
    $empresaActiva = session('empresa_activa') ?? auth()->user()->empresa_id;

    // Parámetros
    $q       = trim($request->query('q', ''));
    $perPage = (int) $request->query('per_page', 10);
    if ($perPage <= 0)  $perPage = 10;
    if ($perPage > 100) $perPage = 100;

    // Query con búsqueda
    $query = Empresa::query();
    if ($q !== '') {
        $query->where('nombre', 'like', "%{$q}%");
    }

    $empresas = $query->orderBy('nombre')->paginate($perPage);

    // Respuesta AJAX para index (tbody + paginación)
    if ($request->ajax()) {
        return response()->json([
            'tbody'      => view('admin.empresas.partials.tbody', compact('empresas'))->render(),
            'pagination' => view('admin.empresas.partials.pagination', compact('empresas'))->render(),
        ]);
    }

    // SSR normal
    return view('admin.empresas.index', [
        'empresas'      => $empresas,
        'empresaActiva' => $empresaActiva,
        'q'             => $q,
        'perPage'       => $perPage,
    ]);
}

    public function create()
    {
        return view('admin.empresas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:empresas,nombre',
        ]);

        Empresa::create($data);

        return redirect()->route('admin.empresas.index')->with('created', true);
    }

    public function edit(Empresa $empresa)
    {
        return view('admin.empresas.edit', compact('empresa'));
    }

    public function update(Request $request, Empresa $empresa)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:empresas,nombre,' . $empresa->id,
        ]);

        $empresa->update($data);

        return redirect()->route('admin.empresas.index')->with('updated', true);
    }

    public function destroy(Empresa $empresa)
    {
        $empresa->delete();
        return redirect()->route('admin.empresas.index')->with('deleted', true);
    }
}

