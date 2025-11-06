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
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\OcAdjuntoController;
use App\Http\Controllers\OcLogController;
use App\Http\Controllers\DevolucionController;

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

/* >>> PDF público por ID con URL firmada (para “Gracias, firmado”) */
Route::get('/public/responsivas/{responsiva}/pdf', [PublicResponsivaController::class, 'pdfById'])
    ->name('public.responsivas.pdf')
    ->middleware('signed');
/* <<< FIN */

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

    // Colaboradores

    Route::resource('colaboradores', ColaboradorController::class)
        ->names('colaboradores')
        ->parameters(['colaboradores' => 'colaborador']);

    // Buscar colaboradores (para selects, etc.)
    Route::get('/api/colaboradores/buscar', [ColaboradorController::class, 'buscar'])
        ->name('api.colaboradores.buscar');
    
    // Historial de colaboradores (modal AJAX)
    Route::get('/colaboradores/{colaborador}/historial', [ColaboradorController::class, 'historial'])
        ->whereNumber('colaborador')
        ->name('colaboradores.historial');
    
    // Fin de Colaboradores

    // Unidades de Servicio

    Route::resource('unidades', UnidadServicioController::class)
        ->names('unidades')
        ->parameters(['unidades' => 'unidad']);
    
    Route::get('/unidades/{unidad}/historial', [UnidadServicioController::class, 'historial'])
        ->name('unidades.historial');

    // Fin de Unidades de Servicio

    // Areas

    Route::resource('areas', AreaController::class)
        ->names('areas')
        ->parameters(['areas' => 'area']);
    
    Route::get('/areas/{area}/historial', [AreaController::class, 'historial'])
        ->name('areas.historial');

    // Fin de Areas

    // Puestos

    Route::resource('puestos', PuestoController::class)
        ->names('puestos')
        ->parameters(['puestos' => 'puesto']);
    
    Route::get('/puestos/{puesto}/historial', [PuestoController::class, 'historial'])
        ->name('puestos.historial');

    // Fin de Puestos

    // Subsidiarias

    Route::resource('subsidiarias', SubsidiariaController::class)
        ->names('subsidiarias')
        ->parameters(['subsidiarias' => 'subsidiaria']);

    // Fin de Subsidiarias

    /*
    |--------------------  Productos  --------------------
    */
    Route::resource('productos', ProductoController::class)
        ->parameters(['productos' => 'producto']);

    // Rutas anidadas de productos
    Route::prefix('productos/{producto}')->group(function () {
        // SERIES (tracking por número de serie)
        Route::get   ('/series',                [ProductoController::class,'series'])->name('productos.series');
        Route::post  ('/series',                [ProductoController::class,'seriesStore'])->name('productos.series.store');
        Route::delete('/series/{serie}',        [ProductoController::class,'seriesDestroy'])->name('productos.series.destroy');
        Route::put   ('/series/{serie}/estado', [ProductoController::class,'seriesEstado'])->name('productos.series.estado');

        // Editar/actualizar serie (anidado al producto)
        Route::get  ('/series/{serie}/edit', [ProductoController::class,'seriesEdit'])->name('productos.series.edit');
        Route::put  ('/series/{serie}',      [ProductoController::class,'seriesUpdate'])->name('productos.series.update');

        // FOTOS de series
        Route::post  ('/series/{serie}/fotos',        [ProductoSerieController::class, 'fotosStore'])->name('productos.series.fotos.store');
        Route::delete('/series/{serie}/fotos/{foto}', [ProductoSerieController::class, 'fotosDestroy'])->name('productos.series.fotos.destroy');

        // EXISTENCIA (tracking por cantidad)
        Route::get ('/existencia',         [ProductoController::class,'existencia'])->name('productos.existencia');
        Route::post('/existencia/ajustar', [ProductoController::class,'existenciaAjustar'])
            ->middleware('permission:productos.edit')
            ->name('productos.existencia.ajustar');
    });

    // Vista directa de series (solo index/show para evitar choques con las rutas anidadas)
    Route::resource('series', ProductoSerieController::class)->only(['index','show']);

    /*
    |--------------------  Responsivas  --------------------
    */
    Route::resource('responsivas', ResponsivaController::class)
        ->only(['index','create','store','show','edit','update','destroy']);

    // PDF (interno)
    Route::get('/responsivas/{responsiva}/pdf', [ResponsivaController::class, 'pdf'])
        ->middleware('permission:responsivas.view')
        ->name('responsivas.pdf');

    // Generar / renovar link de firma
    Route::post('/responsivas/{responsiva}/link', [ResponsivaController::class, 'emitirFirma'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.link');

    // Alias antiguo (compatibilidad)
    Route::post('/responsivas/{responsiva}/emitir-firma', [ResponsivaController::class, 'emitirFirma'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.emitirFirma');

    // Firmar en sitio
    Route::post('/responsivas/{responsiva}/firmar-en-sitio', [ResponsivaController::class, 'firmarEnSitio'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.firmarEnSitio');

    // Eliminar firma
    Route::delete('/responsivas/{responsiva}/firma', [ResponsivaController::class, 'destroyFirma'])
        ->middleware('permission:responsivas.edit')
        ->name('responsivas.firma.destroy');
    
    /*
    |--------------------  Devoluciones  --------------------
    */
    Route::resource('devoluciones', DevolucionController::class)
        ->only(['index','create','store','show','edit','update','destroy'])
        ->middleware(['auth']);

    // PDF interno
    Route::get('/devoluciones/{devolucion}/pdf', [DevolucionController::class, 'pdf'])
        ->name('devoluciones.pdf');

    /*
    |--------------------  Órdenes de Compra (con permisos)  --------------------
    */
    // Crear
    Route::middleware(['auth','permission:oc.create'])->group(function () {
        Route::get('/oc/create', [OrdenCompraController::class, 'create'])->name('oc.create');
        Route::post('/oc',       [OrdenCompraController::class, 'store'])->name('oc.store');
    });

    // Ver / PDFs
    Route::middleware(['auth','permission:oc.view'])->group(function () {
        Route::get('/oc',                      [OrdenCompraController::class, 'index'])->name('oc.index');
        Route::get('/oc/{oc}',                 [OrdenCompraController::class, 'show'])->whereNumber('oc')->name('oc.show');

        Route::get('/oc/{oc}/pdf',             [OrdenCompraController::class, 'pdfOpen'])->whereNumber('oc')->name('oc.pdf.open');
        Route::get('/oc/{oc}/pdf/download',    [OrdenCompraController::class, 'pdfDownload'])->whereNumber('oc')->name('oc.pdf.download');
    });

    // Editar / Actualizar
    Route::middleware(['auth','permission:oc.edit'])->group(function () {
        Route::get('/oc/{oc}/edit', [OrdenCompraController::class, 'edit'])->whereNumber('oc')->name('oc.edit');
        Route::put('/oc/{oc}',      [OrdenCompraController::class, 'update'])->whereNumber('oc')->name('oc.update');
    });

    // Eliminar
    Route::delete('/oc/{oc}', [OrdenCompraController::class, 'destroy'])
        ->middleware(['auth','permission:oc.delete'])
        ->whereNumber('oc')
        ->name('oc.destroy');

    // Estado (solo autenticado; dentro del método validas roles)
    Route::patch('/oc/{oc}/estado', [OrdenCompraController::class, 'updateEstado'])
        ->middleware(['auth'])
        ->whereNumber('oc')
        ->name('oc.estado');

    /*
    |--------------------  Adjuntos OC --------------------
    */
    Route::middleware(['auth'])->group(function () {
        // Modal (HTML)
        Route::get('/oc/{oc}/adjuntos', [OcAdjuntoController::class, 'modal'])
            ->whereNumber('oc')
            ->name('oc.adjuntos.modal');

        // Subida múltiple
        Route::post('/oc/{oc}/adjuntos', [OcAdjuntoController::class, 'store'])
            ->whereNumber('oc')
            ->name('oc.adjuntos.store');

        // Descargar / Eliminar
        Route::get('/oc/adjuntos/{adjunto}/download', [OcAdjuntoController::class, 'download'])
            ->name('oc.adjuntos.download');
        Route::delete('/oc/adjuntos/{adjunto}', [OcAdjuntoController::class, 'destroy'])
            ->name('oc.adjuntos.destroy');
    });

    /*
    |--------------------  Historial OC --------------------
    */
    Route::middleware(['auth','permission:oc.view'])->group(function () {
        Route::get('/oc/{oc}/historial', [OcLogController::class, 'modal'])
            ->whereNumber('oc')
            ->name('oc.historial.modal');
    });
    
    /*
    |--------------------  Proveedores (con permisos)  --------------------
    */
    // Crear 
    Route::middleware(['auth','permission:proveedores.create'])->group(function () {
        Route::get ('/proveedores/create', [ProveedorController::class, 'create'])->name('proveedores.create');
        Route::post('/proveedores',        [ProveedorController::class, 'store'])->name('proveedores.store');
    });

    // Ver / listar / show
    Route::middleware(['auth','permission:proveedores.view'])->group(function () {
        Route::get('/proveedores',             [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('/proveedores/{proveedor}', [ProveedorController::class, 'show'])->whereNumber('proveedor')->name('proveedores.show');
    });

    // Editar / Actualizar
    Route::middleware(['auth','permission:proveedores.edit'])->group(function () {
        Route::get('/proveedores/{proveedor}/edit', [ProveedorController::class, 'edit'])->whereNumber('proveedor')->name('proveedores.edit');
        Route::put('/proveedores/{proveedor}',      [ProveedorController::class, 'update'])->whereNumber('proveedor')->name('proveedores.update');
    });

    // Eliminar
    Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])
        ->middleware(['auth','permission:proveedores.delete'])
        ->whereNumber('proveedor')
        ->name('proveedores.destroy');
});
