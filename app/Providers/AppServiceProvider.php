<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Observers\OrdenCompraObserver;
use App\Observers\OrdenCompraDetalleObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Si no usas este gate, puedes borrarlo.
        Gate::define('admin', fn($user) => $user->rol === 'admin');

        // Registrar observer para logs de OC
        OrdenCompra::observe(OrdenCompraObserver::class);

        OrdenCompraDetalle::observe(OrdenCompraDetalleObserver::class);
    }
}
