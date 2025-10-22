<?php

// app/Observers/OrdenCompraDetalleObserver.php
namespace App\Observers;

use App\Models\OrdenCompraDetalle;
use App\Models\OcLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrdenCompraDetalleObserver
{
    public function created(OrdenCompraDetalle $det): void
    {
        // Ejecuta después del commit para poder ver el log "created" de la OC
        DB::afterCommit(function () use ($det) {
            // Si hay un log de "created" para la misma OC muy cercano al timestamp
            // de esta partida, asumimos que es parte de la creación inicial y NO logueamos "item_added".
            $windowStart = optional($det->created_at)->copy()->subSeconds(8) ?? now()->subSeconds(8);
            $windowEnd   = optional($det->created_at)->copy()->addSeconds(8) ?? now()->addSeconds(8);

            $hasRecentCreation = OcLog::where('orden_compra_id', $det->orden_compra_id)
                ->where('type', 'created')
                ->whereBetween('created_at', [$windowStart, $windowEnd])
                ->exists();

            if ($hasRecentCreation) {
                return; // ← suprime item_added inicial
            }

            // Si NO hubo "created" reciente (es decir, agregaste una partida después),
            // entonces sí registramos "item_added".
            OcLog::create([
                'orden_compra_id' => $det->orden_compra_id,
                'user_id'         => auth()->id(),
                'type'            => 'item_added',
                'data'            => [
                    'id'       => $det->id,
                    'concepto' => $det->concepto,
                    'cantidad' => $det->cantidad,
                    'um'       => $det->unidad,
                    'moneda'   => $det->moneda,
                    'precio'   => $det->precio,
                    'importe'  => $det->importe ?? $det->subtotal,
                    'nota'     => $d->nota ?? null,
                ],
            ]);
        });
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
