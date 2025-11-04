<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Módulos base
        $modules = [
            'colaboradores',
            'areas',
            'unidades',
            'puestos',
            'subsidiarias',
            'productos',
            'responsivas',
            'oc',
            'proveedores',
        ];

        // Acciones base
        $actions = ['view', 'create', 'edit', 'delete'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissionName = "{$module}.{$action}";
                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
            }
        }

        $this->command->info('✅ Permisos creados o actualizados correctamente en la tabla permissions.');
    }
}
