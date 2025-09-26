<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\SubsidiariaController;
use App\Http\Controllers\UnidadServicioController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\PuestoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoSerieController;
use App\Http\Controllers\ResponsivaController;
use App\Http\Controllers\PublicResponsivaController;

/*
|--------------------------------------------------------------------------
| Públicas (sin auth)
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'))->name('home');

// Firma pública (colaborador)
Route::get ('/firmar/{token}', [PublicResponsivaController::class, 'show'])->name('public.sign.show');
Route::post('/firmar/{token}', [PublicResponsivaController::class, 'store'])->name('public.sign.store');

// PDF público para la vista previa (sin auth)
Route::get('/firmar/{token}/pdf', [PublicResponsivaController::class, 'pdf'])
    ->name('public.sign.pdf');

/*
|--------------------------------------------------------------------------
| Dashboard (con auth)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    $empresa = Auth::user()->empresa;
    return view('dashboard', compact('empresa'));
})->middleware(['auth'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Rutas de autenticación y panel admin
|--------------------------------------------------------------------------
*/
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Aplicación (todo bajo auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    /*
    |----------------------  RH  ----------------------
    */
    Route::resource('colaboradores', ColaboradorController::class)
        ->names('colaboradores')
        ->parameters(['colaboradores' => 'colaborador']);

    // Buscar colaboradores (para selects, etc.)
    Route::get('/api/colaboradores/buscar', [ColaboradorController::class, 'buscar'])
        ->name('api.colaboradores.buscar');

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

    /*
    |--------------------  Productos  --------------------
    */
    Route::resource('productos', ProductoController::class)
        ->parameters(['productos' => 'producto']);

    // Bloque de rutas anidadas de productos
    Route::prefix('productos/{producto}')->group(function () {
        // SERIES (tracking por número de serie)
        Route::get   ('/series',                [ProductoController::class,'series'])->name('productos.series');
        Route::post  ('/series',                [ProductoController::class,'seriesStore'])->name('productos.series.store');
        Route::delete('/series/{serie}',        [ProductoController::class,'seriesDestroy'])->name('productos.series.destroy');
        Route::put   ('/series/{serie}/estado', [ProductoController::class,'seriesEstado'])->name('productos.series.estado');

        // FOTOS de series
        Route::post  ('/series/{serie}/fotos',        [ProductoSerieController::class, 'fotosStore'])->name('productos.series.fotos.store');
        Route::delete('/series/{serie}/fotos/{foto}', [ProductoSerieController::class, 'fotosDestroy'])->name('productos.series.fotos.destroy');

        // EXISTENCIA (tracking por cantidad)
        Route::get ('/existencia',             [ProductoController::class,'existencia'])->name('productos.existencia');
        Route::post('/existencia/ajustar',     [ProductoController::class,'existenciaAjustar'])->name('productos.existencia.ajustar');

        Route::post('/existencia/ajustar', [ProductoController::class,'existenciaAjustar'])
            ->middleware('permission:productos.edit') // SOLO con permiso se puede mover stock
            ->name('productos.existencia.ajustar');
    });

    // Vista directa de series (si la usas)
    Route::resource('series', ProductoSerieController::class)->only(['index','edit','update','show']);

    /*
    |--------------------  Responsivas  --------------------
    */
    Route::resource('responsivas', ResponsivaController::class)
        ->only(['index','create','store','show','edit','update','destroy']);

    // PDF (interno) — requiere permiso de ver responsivas
    Route::get('/responsivas/{responsiva}/pdf', [ResponsivaController::class, 'pdf'])
        ->middleware('permission:responsivas.view')
        ->name('responsivas.pdf');

    // Generar / renovar link de firma — requiere permiso de editar responsivas
    Route::post('/responsivas/{responsiva}/link', [ResponsivaController::class, 'emitirFirma'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.link');

    // Alias antiguo para compatibilidad
    Route::post('/responsivas/{responsiva}/emitir-firma', [ResponsivaController::class, 'emitirFirma'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.emitirFirma');

    // Firmar en sitio — requiere permiso de editar responsivas
    Route::post('/responsivas/{responsiva}/firmar-en-sitio', [ResponsivaController::class, 'firmarEnSitio'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.firmarEnSitio');

    // Eliminar Firma — requiere permiso de editar responsivas    
    Route::delete('/responsivas/{responsiva}/firma', [ResponsivaController::class, 'destroyFirma'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.firma.destroy');
});
