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
use App\Http\Controllers\DevolucionFirmaLinkController;
use App\Http\Controllers\CartuchoController;

/*
|--------------------------------------------------------------------------
| Públicas (sin auth)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

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

// Rutas públicas para firmar SIN login
Route::get('/firmar-devolucion/{token}', 
    [DevolucionFirmaLinkController::class, 'showForm']
)->name('devoluciones.firmaExterna.show');

Route::post('/firmar-devolucion/{token}', 
    [DevolucionFirmaLinkController::class, 'guardarFirma']
)->name('devoluciones.firmaExterna.store');

// ===== CARTUCHOS (PUBLICO) =====
Route::get('/firmar-cartucho/{token}', [CartuchoController::class, 'firmaPublica'])
    ->name('cartuchos.firma.publica');

Route::post('/firmar-cartucho/{token}', [CartuchoController::class, 'firmaPublicaStore'])
    ->name('cartuchos.firma.publica.store');

Route::get('/firmar-cartucho/{token}/pdf', [CartuchoController::class, 'pdfPublico'])
    ->name('cartuchos.firma.publica.pdf');

Route::get('/firmar-cartucho/{token}/thumb', [CartuchoController::class, 'thumbPublico'])
    ->name('cartuchos.firma.publica.thumb');

Route::get('/firmar-cartucho/ok/{cartucho}', [CartuchoController::class, 'firmaPublicaOk'])
    ->middleware('signed')
    ->name('cartuchos.firma.publica.ok');

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

    Route::get('/subsidiarias/{subsidiaria}/historial', [SubsidiariaController::class, 'historial'])
        ->name('subsidiarias.historial');

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

    Route::get('productos/{producto}/historial', [ProductoController::class, 'historial'])
        ->name('productos.historial');

    Route::get('/producto-series/{productoSerie}/historial', [ProductoSerieController::class, 'historial'])
    ->name('producto-series.historial');
    
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
    
    // Historial de Responsivas (modal AJAX)
    Route::get('/responsivas/{responsiva}/historial', [ResponsivaController::class, 'historial'])
        ->whereNumber('responsiva')
        ->name('responsivas.historial');

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
    |--------------------  Celulares (módulo)  --------------------
    */
    Route::prefix('celulares')->name('celulares.')->group(function () {

        // ===== Responsivas Celulares =====
        Route::get('/responsivas', [ResponsivaController::class, 'indexCelulares'])
            ->middleware('permission:celulares.view')
            ->name('responsivas.index');

        Route::get('/responsivas/create', [ResponsivaController::class, 'createCelulares'])
            ->middleware('permission:celulares.create')
            ->name('responsivas.create');

        Route::post('/responsivas', [ResponsivaController::class, 'storeCelulares'])
            ->middleware('permission:celulares.create')
            ->name('responsivas.store');

        // (Opcional pero recomendado si vas a editar/eliminar desde el index de celulares)
        Route::get('/responsivas/{responsiva}/edit', [ResponsivaController::class, 'editCelulares'])
            ->middleware('permission:celulares.edit')
            ->name('responsivas.edit');

        Route::put('/responsivas/{responsiva}', [ResponsivaController::class, 'updateCelulares'])
            ->middleware('permission:celulares.edit')
            ->name('responsivas.update');

        Route::delete('/responsivas/{responsiva}', [ResponsivaController::class, 'destroyCelulares'])
            ->middleware('permission:celulares.delete')
            ->name('responsivas.destroy');


        // ===== Devoluciones Celulares =====
        Route::get('/devoluciones/create', [DevolucionController::class, 'create'])
            ->middleware('permission:celulares.create')
            ->name('devoluciones.create');

        Route::post('/devoluciones', [DevolucionController::class, 'store'])
            ->middleware('permission:celulares.create')
            ->name('devoluciones.store');

        Route::get('/devoluciones/{devolucion}', [DevolucionController::class, 'show'])
            ->middleware('permission:celulares.view')
            ->name('devoluciones.show');

        Route::get('/devoluciones/{devolucion}/pdf', [DevolucionController::class, 'pdf'])
            ->middleware('permission:celulares.view')
            ->name('devoluciones.pdf');

        Route::get('/devoluciones/{devolucion}/edit', [DevolucionController::class, 'edit'])
            ->middleware('permission:celulares.edit')
            ->name('devoluciones.edit');

        Route::put('/devoluciones/{devolucion}', [DevolucionController::class, 'update'])
            ->middleware('permission:celulares.edit')
            ->name('devoluciones.update');

        Route::delete('/devoluciones/{devolucion}', [DevolucionController::class, 'destroy'])
            ->middleware('permission:celulares.delete')
            ->name('devoluciones.destroy');
    });

    /*
    |--------------------  Devoluciones  --------------------
    */
    Route::resource('devoluciones', DevolucionController::class)
        ->only(['index','create','store','show','edit','update','destroy'])
        ->middleware(['auth']);

    // PDF interno
    Route::get('/devoluciones/{devolucion}/pdf', [DevolucionController::class, 'pdf'])
        ->name('devoluciones.pdf');

    Route::post('devoluciones/{devolucion}/firmar-en-sitio', [DevolucionController::class, 'firmarEnSitio'])
        ->name('devoluciones.firmarEnSitio');

    Route::delete('/devoluciones/{devolucion}/firma', [DevolucionController::class, 'borrarFirmaEnSitio'])
        ->name('devoluciones.borrarFirmaEnSitio');
    
    Route::middleware(['auth'])->group(function () {
        Route::post('/devoluciones/{devolucion}/generar-link-firma', 
            [DevolucionFirmaLinkController::class, 'generarLink']
        )->name('devoluciones.generarLinkFirma');
    });

    /*
    |--------------------  Cartuchos  --------------------
    */
    Route::resource('cartuchos', CartuchoController::class)
    ->only(['index','create','store','show','edit','update','destroy']);

    // PDF interno
    Route::get('/cartuchos/{cartucho}/pdf', [CartuchoController::class, 'pdf'])
        ->middleware('permission:cartuchos.view')
        ->name('cartuchos.pdf');

    // Firmar en sitio (canvas en show interno)
    Route::post('/cartuchos/{cartucho}/firmar-en-sitio', [CartuchoController::class, 'firmarEnSitio'])
        ->middleware('permission:cartuchos.edit')
        ->name('cartuchos.firmarEnSitio');

    // Generar link firma pública
    Route::post('/cartuchos/{cartucho}/link', [CartuchoController::class, 'emitirFirma'])
        ->middleware('permission:cartuchos.edit')
        ->name('cartuchos.link');

    // Borrar firma (opcional)
    Route::delete('/cartuchos/{cartucho}/firma', [CartuchoController::class, 'destroyFirma'])
        ->middleware('permission:cartuchos.edit')
        ->name('cartuchos.firma.destroy');

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
    
    // Recepción  🟢 NUEVO
    Route::patch('/oc/{oc}/recepcion', [OrdenCompraController::class, 'updateRecepcion'])
        ->middleware(['auth'])
        ->whereNumber('oc')
        ->name('oc.recepcion');

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
