<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\RoleController;

Route::middleware(['web','auth','role:Administrador'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        // Panel
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');

        // Usuarios
        Route::resource('users', UserController::class);
        Route::post('/cambiar-empresa', [UserController::class, 'cambiarEmpresa'])
            ->name('cambiarEmpresa');

        // Empresas (CRUD completo)
        Route::resource('empresas', EmpresaController::class)
            ->names('empresas'); // admin.empresas.*

        // Roles
        Route::resource('roles', RoleController::class)->except('show');
    });
