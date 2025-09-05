<?php

namespace App\Http\Controllers;

use App\Models\ProductoSerie;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductoSerieFoto;

class ProductoSerieController extends Controller implements HasMiddleware
{
    /** Nueva forma de registrar middleware en Laravel 11/12 */
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            // Descomenta si usas permisos:
            // new Middleware('permission:productos.view', only: ['show','edit']),
            // new Middleware('permission:productos.edit', only: ['edit','update']),
        ];
    }

    /* ================ Helpers de tenant ================= */
    private function tenantId(): ?int
    {
        return auth()->check()
            ? (int) session('empresa_activa', auth()->user()->empresa_id)
            : null;
    }

    private function ensureTenant(ProductoSerie $series): void
    {
        $tenant = $this->tenantId();
        if ($tenant && $series->empresa_tenant_id && $series->empresa_tenant_id !== $tenant) {
            abort(404);
        }
    }

    /* =================== Vistas =================== */
    public function edit(ProductoSerie $series)
    {
        $this->ensureTenant($series);
        // $series->specs ya es merge (producto + overrides)
        $series->load('producto');
        return view('series.edit', ['s' => $series]);
    }

    public function update(Request $r, ProductoSerie $series)
    {
        $this->ensureTenant($series);

        $r->validate([
            'spec.ram_gb'                               => ['nullable','integer','min:1','max:32767'],
            'spec.color'                                => ['nullable','string','max:50'],
            'spec.almacenamiento.tipo'                  => ['nullable','in:ssd,hdd,m2'],
            'spec.almacenamiento.capacidad_gb'          => ['nullable','integer','min:1','max:50000'],
            'spec.procesador'                           => ['nullable','string','max:120'],
        ]);

        // Overrides recibidos
        $over = $r->input('spec', []);

        // Normaliza numéricos para que queden como number en JSON (no string)
        if (array_key_exists('ram_gb', $over) && $over['ram_gb'] !== '' && $over['ram_gb'] !== null) {
            $over['ram_gb'] = (int) $over['ram_gb'];
        }
        if (isset($over['almacenamiento']['capacidad_gb']) &&
            $over['almacenamiento']['capacidad_gb'] !== '' &&
            $over['almacenamiento']['capacidad_gb'] !== null) {
            $over['almacenamiento']['capacidad_gb'] = (int) $over['almacenamiento']['capacidad_gb'];
        }

        // Limpia vacíos y nulls para no pisar valores del producto
        $over = $this->clean($over);

        // Guarda solo si hay overrides; si no, deja null
        $series->especificaciones = $over ?: null;
        $series->save();

        // Redirige a la lista de series del producto con aviso
        $series->loadMissing('producto');
        return redirect()
            ->route('productos.series', $series->producto)
            ->with('updated', 'Serie actualizada.');
    }

    public function show(ProductoSerie $series)
    {
        $this->ensureTenant($series);
        $series->load('producto');
        return view('series.show', ['s' => $series]);
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

    public function fotosStore(\App\Models\Producto $producto, \App\Models\ProductoSerie $serie, Request $req)
{
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

public function fotosDestroy(\App\Models\Producto $producto, \App\Models\ProductoSerie $serie, \App\Models\ProductoSerieFoto $foto)
{
    // seguridad simple por relación
    abort_unless($foto->producto_serie_id === $serie->id, 404);

    Storage::disk('public')->delete($foto->path);
    $foto->delete();

    return back()->with('updated', 'Foto eliminada.');
}
}
