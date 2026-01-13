<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SpecificPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $specificPermissions = [
            ['name' => 'cartuchos.view', 'guard_name' => 'web'],
            ['name' => 'cartuchos.create', 'guard_name' => 'web'],
            ['name' => 'cartuchos.edit', 'guard_name' => 'web'],
            ['name' => 'cartuchos.delete', 'guard_name' => 'web'],
        ];

        foreach ($specificPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission['name'],
                'guard_name' => $permission['guard_name'],
            ]);
        }

        $this->command->info('Permisos del 41 al 44 creados o actualizados correctamente en la tabla permissions.');
    }
}
