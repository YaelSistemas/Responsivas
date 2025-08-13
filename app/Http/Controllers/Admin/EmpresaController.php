<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;

class EmpresaController extends Controller
{
    public function index()
    {
        // Si ya usas empresa_activa en sesión, la mostramos marcada
        $empresaActiva = session('empresa_activa') ?? auth()->user()->empresa_id;

        $empresas = Empresa::orderBy('nombre')->paginate(15);

        return view('admin.empresas.index', compact('empresas','empresaActiva'));
    }
}
