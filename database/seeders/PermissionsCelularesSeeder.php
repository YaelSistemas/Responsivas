<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsCelularesSeeder extends Seeder
{
    public function run(): void
    {
        $specificPermissions = [
            ['name' => 'celulares.view',   'guard_name' => 'web'],
            ['name' => 'celulares.create', 'guard_name' => 'web'],
            ['name' => 'celulares.edit',   'guard_name' => 'web'],
            ['name' => 'celulares.delete', 'guard_name' => 'web'],
        ];

        foreach ($specificPermissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission['name'],
                'guard_name' => $permission['guard_name'],
            ]);
        }

        $this->command->info('Permisos de celulares (view/create/edit/delete) creados o ya existentes en permissions.');
    }
}
