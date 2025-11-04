<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Crear el rol si no existe
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

        // Crear o actualizar el usuario
        $user = User::updateOrCreate(
            ['email' => 'admin@vysisa.mx'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('12345678'),
            ]
        );

        // Asignar el rol de Administrador
        $user->syncRoles([$role]);

        // Mensaje en consola
        $this->command->info('✅ Usuario administrador creado: admin@vysisa.mx / 12345678');
    }
}
