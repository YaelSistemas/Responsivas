<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\SubsidiariaController;
use App\Http\Controllers\UnidadServicioController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\PuestoController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $empresa = Auth::user()->empresa;
    return view('dashboard', compact('empresa'));
})->middleware(['auth'])->name('dashboard');

/* Eliminado porque el sistema ya gestiona usuarios desde el panel admin
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});*/

// Montar rutas del panel admin
require __DIR__.'/admin.php';

// Rutas de autenticación (Laravel Breeze, Jetstream, etc.)
require __DIR__.'/auth.php';

// Colaboradores
Route::middleware(['auth'])->group(function () {
    Route::resource('colaboradores', ColaboradorController::class)
        ->names('colaboradores')
        ->parameters([
            'colaboradores' => 'colaborador'
        ]);
});

// Para Buscar Colaboradores en Unidades
Route::middleware(['auth'])->get(
    '/api/colaboradores/buscar',
    [\App\Http\Controllers\ColaboradorController::class, 'buscar']
)->name('api.colaboradores.buscar');

// RH en el sistema normal (siguen siendo solo para Administrador)
Route::middleware(['auth'])->group(function () {

    Route::resource('unidades', UnidadServicioController::class)
        ->names('unidades')
        ->parameters(['unidades' => 'unidad']);

    Route::resource('areas', AreaController::class)
        ->names('areas')
        ->parameters(['areas' => 'area']);

    Route::resource('puestos', PuestoController::class)
        ->names('puestos')
        ->parameters(['puestos' => 'puesto']);

    Route::resource('subsidiarias', SubsidiariaController::class)
        ->names('subsidiarias')
        ->parameters(['subsidiarias' => 'subsidiaria']);
});

