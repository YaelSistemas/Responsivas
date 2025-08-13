<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Role; // tu modelo
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Permisos agrupados por módulo (prefijo).
     * Clave = prefijo; valor = [accion => etiqueta]
     */
    private array $permGroups = [
        'colaboradores' => [
            'view'   => 'Ver colaboradores',
            'create' => 'Crear colaboradores',
            'edit'   => 'Editar colaboradores',
            'delete' => 'Eliminar colaboradores',
        ],
        'unidades' => [
            'view'   => 'Ver unidades',
            'create' => 'Crear unidades',
            'edit'   => 'Editar unidades',
            'delete' => 'Eliminar unidades',
        ],
        'areas' => [
            'view'   => 'Ver áreas',
            'create' => 'Crear áreas',
            'edit'   => 'Editar áreas',
            'delete' => 'Eliminar áreas',
        ],
        'puestos' => [
            'view'   => 'Ver puestos',
            'create' => 'Crear puestos',
            'edit'   => 'Editar puestos',
            'delete' => 'Eliminar puestos',
        ],
        'subsidiarias' => [
            'view'   => 'Ver subsidiarias',
            'create' => 'Crear subsidiarias',
            'edit'   => 'Editar subsidiarias',
            'delete' => 'Eliminar subsidiarias',
        ],
    ];

    public function index(Request $request)
    {
        $q       = $request->query('q');
        $perPage = $request->integer('per_page', 10);

        $roles = Role::with('permissions')
            ->when($q, function ($qr) use ($q) {
                $qr->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('display_name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                })->orWhereHas('permissions', function ($p) use ($q) {
                    $p->where('name', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'tbody'      => view('admin.roles.partials.tbody', compact('roles'))->render(),
                'pagination' => view('admin.roles.partials.pagination', compact('roles'))->render(),
            ]);
        }

        return view('admin.roles.index', compact('roles', 'q', 'perPage'));
    }

    public function create()
    {
        $groups = $this->normalizedGroups();   // nombre + etiqueta listos para la vista
        return view('admin.roles.create', compact('groups'));
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'name'         => ['required','string','max:100','unique:roles,name'],
        'display_name' => ['nullable','string','max:100'],
        'description'  => ['nullable','string','max:2000'],
        'permissions'  => ['array'],
        'permissions.*'=> ['string','exists:permissions,name'],
    ]);

    $role = Role::create([
        'name'         => $data['name'],
        'guard_name'   => 'web',
        'display_name' => $data['display_name'] ?? null,
        'description'  => $data['description'] ?? null,
    ]);

    $role->syncPermissions($data['permissions'] ?? []);

    return redirect()->route('admin.roles.index')->with('success','Rol creado.');
}

    public function edit(Role $role)
    {
        $groups          = $this->normalizedGroups();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('admin.roles.edit', compact('role','groups','rolePermissions'));
    }

    public function update(Request $request, Role $role)
{
    $data = $request->validate([
        'name'         => ['required','string','max:100', Rule::unique('roles','name')->ignore($role->id)],
        'display_name' => ['nullable','string','max:100'],
        'description'  => ['nullable','string','max:2000'],
        'permissions'  => ['array'],
        'permissions.*'=> ['string','exists:permissions,name'],
    ]);

    $role->update([
        'name'         => $data['name'],
        'display_name' => $data['display_name'] ?? null,
        'description'  => $data['description'] ?? null,
    ]);

    $role->syncPermissions($data['permissions'] ?? []);

    return redirect()->route('admin.roles.index')->with('success','Rol actualizado.');
}


    public function destroy(Role $role)
    {
        if ($role->name === 'Administrador') {
            return back()->with('error','No puedes eliminar el rol Administrador.');
        }

        $role->delete();
        return back()->with('success','Rol eliminado.');
    }

    /* ========== Helpers ========== */

    // Lista plana de permisos: ["colaboradores.view","colaboradores.create", ...]
    private function allPermissionNames(): array
    {
        $list = [];
        foreach ($this->permGroups as $prefix => $actions) {
            foreach ($actions as $action => $label) {
                $list[] = "{$prefix}.{$action}";
            }
        }
        return $list;
    }

    // Estructura para la vista: ['colaboradores' => [ ['name'=>'colaboradores.view','label'=>'Ver colaboradores'], ... ]]
    private function normalizedGroups(): array
    {
        $out = [];
        foreach ($this->permGroups as $prefix => $actions) {
            $out[$prefix] = [];
            foreach ($actions as $action => $label) {
                $out[$prefix][] = [
                    'name'  => "{$prefix}.{$action}",
                    'label' => $label,
                ];
            }
        }
        return $out;
    }
}
