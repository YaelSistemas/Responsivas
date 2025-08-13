<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\RoleController;

Route::middleware(['web','auth','role:Administrador'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        // 👉 Ruta raíz del panel
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');

        Route::resource('users', UserController::class);
        Route::post('/cambiar-empresa', [UserController::class, 'cambiarEmpresa'])
            ->name('cambiarEmpresa');

        Route::get('/empresas', [EmpresaController::class, 'index'])->name('empresas.index');

        Route::resource('roles', RoleController::class)->except('show');
    });
