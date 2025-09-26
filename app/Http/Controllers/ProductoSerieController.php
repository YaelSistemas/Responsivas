<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoSerie;
use App\Models\ProductoSerieFoto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

class ProductoSerieController extends Controller implements HasMiddleware
{
    /** Middleware (Laravel 11/12) */
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            // new Middleware('permission:productos.view',  only: ['show','edit']),
            // new Middleware('permission:productos.edit',  only: ['edit','update','fotosStore','fotosDestroy']),
        ];
    }

    /* ================ Helpers de tenant ================= */
    private function tenantId(): ?int
    {
        return auth()->check()
            ? (int) session('empresa_activa', auth()->user()->empresa_id)
            : null;
    }

    private function ensureTenant(ProductoSerie $serie): void
    {
        $tenant = $this->tenantId();
        if ($tenant && $serie->empresa_tenant_id && $serie->empresa_tenant_id !== $tenant) {
            abort(404);
        }
    }

    /** Limpia strings vacíos y nulls en arreglos anidados */
    private function clean($arr)
    {
        if (!is_array($arr)) return $arr;
        $out = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $vv = $this->clean($v);
                if ($vv !== [] && $vv !== null) $out[$k] = $vv;
            } else {
                if ($v !== '' && $v !== null) $out[$k] = $v;
            }
        }
        return $out;
    }

    /* =================== Vistas =================== */
    /**
     * IMPORTANTE: esta acción corresponde a la ruta anidada:
     * GET /productos/{producto}/series/{serie}/edit   -> name: productos.series.edit
     */
    public function edit(Producto $producto, ProductoSerie $serie)
    {
        $this->ensureTenant($serie);
        // Cargamos el producto por si llegaran a llamar la ruta sin él
        $serie->loadMissing('producto');

        if ($producto->tipo === 'equipo_pc') {
            // Vista completa de overrides de especificaciones
            // (compatibilidad: algunas vistas esperan $s)
            return view('series.edit', [
                'producto' => $producto,
                'serie'    => $serie,
                's'        => $serie,
            ]);
        }

        // Para impresora/monitor/pantalla/periférico/otro -> solo descripción
        return view('series.edit_desc', [
            'producto'    => $producto,
            'serie'       => $serie,
            'descripcion' => $serie->observaciones, // usamos 'observaciones' como descripción
        ]);
    }

    /**
     * PUT /productos/{producto}/series/{serie}  -> name: productos.series.update
     */
    public function update(Request $r, Producto $producto, ProductoSerie $serie)
    {
        $this->ensureTenant($serie);

        if ($producto->tipo === 'equipo_pc') {
            // Validación de overrides
            $r->validate([
                'spec.ram_gb'                              => ['nullable','integer','min:1','max:32767'],
                'spec.color'                               => ['nullable','string','max:50'],
                'spec.almacenamiento.tipo'                 => ['nullable','in:ssd,hdd,m2'],
                'spec.almacenamiento.capacidad_gb'         => ['nullable','integer','min:1','max:50000'],
                'spec.procesador'                          => ['nullable','string','max:120'],
            ]);

            // Overrides recibidos
            $over = $r->input('spec', []);

            // Normaliza numéricos
            if (array_key_exists('ram_gb', $over) && $over['ram_gb'] !== '' && $over['ram_gb'] !== null) {
                $over['ram_gb'] = (int) $over['ram_gb'];
            }
            if (isset($over['almacenamiento']['capacidad_gb']) &&
                $over['almacenamiento']['capacidad_gb'] !== '' &&
                $over['almacenamiento']['capacidad_gb'] !== null) {
                $over['almacenamiento']['capacidad_gb'] = (int) $over['almacenamiento']['capacidad_gb'];
            }

            // Limpia vacíos y nulls
            $over = $this->clean($over);

            // Guarda solo si hay overrides; si no, deja null
            $serie->especificaciones = $over ?: null;
            $serie->save();
        } else {
            // Solo descripción
            $data = $r->validate([
                'descripcion' => ['nullable','string','max:2000'],
            ]);
            $serie->observaciones = $data['descripcion'] ?? null;
            $serie->save();
        }

        return redirect()
            ->route('productos.series', $producto)
            ->with('updated', 'Serie actualizada.');
    }

    /* (Opcional) para rutas sueltas tipo /series/{series} */
    public function show(ProductoSerie $series)
    {
        $this->ensureTenant($series);
        $series->load('producto');
        return view('series.show', ['s' => $series]);
    }

    /* =================== FOTOS =================== */

    public function fotosStore(Producto $producto, ProductoSerie $serie, Request $req)
    {
        $this->ensureTenant($serie);

        $req->validate([
            'imagenes'   => ['required','array','min:1'],
            'imagenes.*' => ['image','max:4096'], // 4MB c/u
            'caption'    => ['nullable','string','max:200'],
        ]);

        foreach ($req->file('imagenes') as $img) {
            $path = $img->store("series/{$serie->id}", 'public');
            $serie->fotos()->create([
                'path'    => $path,
                'caption' => $req->input('caption'),
            ]);
        }

        return back()->with('updated', 'Fotos subidas.');
    }

    public function fotosDestroy(Producto $producto, ProductoSerie $serie, ProductoSerieFoto $foto)
    {
        $this->ensureTenant($serie);

        // seguridad simple por relación
        abort_unless($foto->producto_serie_id === $serie->id, 404);

        Storage::disk('public')->delete($foto->path);
        $foto->delete();

        return back()->with('updated', 'Foto eliminada.');
    }
}
