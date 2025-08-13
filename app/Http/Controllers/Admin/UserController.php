<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Listado
     */
    public function index(Request $request)
{
    $auth    = Auth::user();
    $q       = trim($request->query('q', ''));
    $perPage = (int) $request->query('per_page', 10);
    if ($perPage <= 0)  $perPage = 10;
    if ($perPage > 100) $perPage = 100;

    $query = User::with(['empresa','roles']);

    // Si NO es Administrador (Spatie), solo ve su empresa
    if (!$auth->hasRole('Administrador')) {
        $query->where('empresa_id', $auth->empresa_id);
    }

    // Búsqueda
    if ($q !== '') {
        $query->where(function ($qq) use ($q) {
            $qq->where('name', 'like', "%{$q}%")
               ->orWhere('email', 'like', "%{$q}%")
               ->orWhereHas('roles', fn($r)=> $r->where('name','like',"%{$q}%"))
               ->orWhereHas('empresa', fn($e)=> $e->where('nombre','like',"%{$q}%"));
        });
    }

    $users = $query->orderBy('name')->paginate($perPage);

    // Respuesta AJAX para el index con debounce/paginación dinámica
    if ($request->ajax()) {
        return response()->json([
            'tbody'      => view('admin.users.partials.tbody', compact('users'))->render(),
            'pagination' => view('admin.users.partials.pagination', compact('users'))->render(),
        ]);
    }

    // SSR normal
    return view('admin.users.index', [
        'users'   => $users,
        'q'       => $q,
        'perPage' => $perPage,
    ]);
}


    /**
     * Form de creación
     */
    public function create()
    {
        $empresas = Empresa::all();
        $roles    = Role::orderBy('name')->get(); // 👈 roles Spatie
        return view('admin.users.create', compact('empresas','roles'));
    }

    /**
     * Guardar
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:6',
            'empresa_id' => 'required|exists:empresas,id',
            'activo'     => 'required|boolean',
            'roles'      => 'required|array',            // 👈 ahora vienen de Spatie
            'roles.*'    => 'string|exists:roles,name',  // nombres de rol válidos
        ]);

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => bcrypt($request->password),
            'empresa_id' => $request->empresa_id,
            'activo'     => $request->activo,
        ]);

        // Asignar roles (por nombre)
        $user->syncRoles($request->roles);

        return redirect()->route('admin.users.index')->with('created', true);
    }

    /**
     * Form de edición
     */
    public function edit($id)
    {
        $user     = User::with('roles')->findOrFail($id);
        $empresas = Empresa::all();
        $roles    = Role::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'empresas','roles'));
    }

    /**
     * Actualizar
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'empresa_id' => 'required|exists:empresas,id',
            'activo'     => 'required|boolean',
            'password'   => 'nullable|string|min:6',
            'roles'      => 'required|array',
            'roles.*'    => 'string|exists:roles,name',
        ]);

        $user->name       = $request->name;
        $user->email      = $request->email;
        $user->empresa_id = $request->empresa_id;
        $user->activo     = $request->activo;

        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        // Actualizar roles
        $user->syncRoles($request->roles);

        return redirect()->route('admin.users.index')->with('updated', true);
    }

    /**
     * Eliminar
     */
    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('deleted', true);
    }

    public function cambiarEmpresa(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
        ]);

        session(['empresa_activa' => $request->empresa_id]);

        return back();
    }
}
