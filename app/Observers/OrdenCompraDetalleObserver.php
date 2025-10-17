<?php

// app/Observers/OrdenCompraDetalleObserver.php
namespace App\Observers;

use App\Models\OrdenCompraDetalle;
use App\Models\OcLog;
use Illuminate\Support\Facades\Auth;

class OrdenCompraDetalleObserver
{
    public function created(OrdenCompraDetalle $d): void
    {
        OcLog::create([
            'orden_compra_id' => $d->orden_compra_id,
            'user_id' => Auth::id(),
            'type' => 'item_added',
            'data' => [
                'id'       => $d->id,
                'cantidad' => $d->cantidad,
                'um'       => $d->unidad ?? null,
                'concepto' => $d->concepto,
                'moneda'   => $d->moneda,
                'precio'   => $d->precio,
                'importe'  => $d->importe ?? $d->subtotal ?? null,
                'nota'     => $d->nota ?? null,
            ],
        ]);
    }

    public function updating(OrdenCompraDetalle $d): void
    {
        $watch = ['cantidad','unidad','concepto','moneda','precio','importe','subtotal','iva_pct','iva_monto','total','nota'];
        $diff = [];
        foreach ($d->getDirty() as $field => $new) {
            if (!in_array($field, $watch, true)) continue;
            $old = $d->getOriginal($field);
            if ($old === $new) continue;
            $diff[$field] = ['from' => $old, 'to' => $new];
        }
        if (!$diff) return;

        OcLog::create([
            'orden_compra_id' => $d->orden_compra_id,
            'user_id' => Auth::id(),
            'type' => 'item_updated',
            'data' => [
                'id'       => $d->id,
                'concepto' => $d->concepto,
                'changes'  => $diff,
            ],
        ]);
    }

    public function deleted(OrdenCompraDetalle $d): void
    {
        OcLog::create([
            'orden_compra_id' => $d->orden_compra_id,
            'user_id' => Auth::id(),
            'type' => 'item_removed',
            'data' => [
                'id'       => $d->id,
                'cantidad' => $d->cantidad,
                'um'       => $d->unidad ?? null,
                'concepto' => $d->concepto,
                'moneda'   => $d->moneda,
                'precio'   => $d->precio,
                'importe'  => $d->importe ?? $d->subtotal ?? null,
                'nota'     => $d->nota ?? null,
            ],
        ]);
    }
}
