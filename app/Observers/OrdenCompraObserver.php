<?php

namespace App\Observers;

use App\Models\OrdenCompra;
use App\Models\OcLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class OrdenCompraObserver
{
    protected static array $seen = [];

    /** Normaliza valores para evitar falsos positivos en updated() */
    protected function norm(string $field, $value)
    {
        if ($value === null) return null;

        if (in_array($field, ['solicitante_id', 'proveedor_id'], true)) {
            return (string) (int) $value;
        }

        if (in_array($field, ['iva_porcentaje','subtotal','iva_monto','monto'], true)) {
            return (string) (float) $value;
        }

        if ($field === 'fecha') {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * Log de creación (una sola vez, después de que se confirmen cabecera y partidas).
     */
    public function created(OrdenCompra $oc): void
    {
        // Ejecutar después de que termine la transacción del store()
        DB::afterCommit(function () use ($oc) {
            $oc->refresh(); // <-- asegura que ya tenga seq, totales y relaciones consolidadas
            // Cargar relaciones consolidadas
            if (method_exists($oc, 'solicitante')) $oc->loadMissing('solicitante');
            if (method_exists($oc, 'proveedor'))   $oc->loadMissing('proveedor');
            if (method_exists($oc, 'detalles'))    $oc->loadMissing('detalles');

            // Si tu cabecera no tiene subtotal/iva/total, tomarlos de las partidas
            $sumSubtotal = $oc->detalles->sum(fn($d) => (float) ($d->subtotal ?? $d->importe ?? 0));
            $sumIva      = $oc->detalles->sum(fn($d) => (float) ($d->iva_monto ?? 0));
            $sumTotal    = $oc->detalles->sum(function($d){
                $sub = (float) ($d->subtotal ?? $d->importe ?? 0);
                $iva = (float) ($d->iva_monto ?? 0);
                return (float) ($d->total ?? ($sub + $iva));
            });

            $ivaPctFallback = optional($oc->detalles->first())->iva_pct;
            $ivaPctCabecera = Schema::hasColumn($oc->getTable(),'iva_porcentaje') ? $oc->iva_porcentaje : null;
            $ivaPctFinal    = is_numeric($ivaPctCabecera) ? (float)$ivaPctCabecera
                                : (is_numeric($ivaPctFallback) ? (float)$ivaPctFallback : null);

            $solName = $oc->solicitante
                ? trim(($oc->solicitante->nombre ?? '').' '.($oc->solicitante->apellidos ?? ''))
                : null;

            $payload = [
                'numero_orden'   => $oc->numero_orden,
                'fecha'          => $oc->fecha ? Carbon::parse($oc->fecha)->format('Y-m-d') : null,
                'solicitante'    => $solName ?? ($oc->solicitante?->name ?? $oc->solicitante_id),
                'proveedor'      => $oc->proveedor?->nombre ?? $oc->proveedor_id,
                'descripcion'    => $oc->descripcion,

                // Cabecera si existe; si no, sumas de partidas
                'iva_porcentaje' => $ivaPctFinal,
                'subtotal'       => Schema::hasColumn($oc->getTable(),'subtotal')  ? $oc->subtotal  : $sumSubtotal,
                'iva'            => Schema::hasColumn($oc->getTable(),'iva_monto') ? $oc->iva_monto : $sumIva,
                'total'          => $oc->monto ?? ($oc->total ?? $sumTotal),

                'notas'          => Schema::hasColumn($oc->getTable(),'notas') ? $oc->notas : null,
                'estado'         => $oc->estado,

                'items'          => method_exists($oc, 'detalles')
                    ? $oc->detalles->map(function($d){
                        return [
                            'id'       => $d->id,
                            'cantidad' => $d->cantidad,
                            'um'       => $d->unidad ?? ($d->unidad_medida ?? $d->um ?? null),
                            'concepto' => $d->concepto,
                            'moneda'   => $d->moneda,
                            'precio'   => $d->precio ?? ($d->precio_unitario ?? null),
                            'importe'  => $d->importe ?? ($d->subtotal ?? null),
                            'nota'     => $d->nota ?? null,
                        ];
                    })->values()->all()
                    : [],
            ];

            // 🔒 anti-duplicado:
            if (OcLog::where('orden_compra_id', $oc->id)
                    ->where('type', 'created')
                    ->exists()) {
                return;
            }
            
            OcLog::create([
                'orden_compra_id' => $oc->id,
                'user_id'         => Auth::id(),
                'type'            => 'created',
                'data'            => $payload,
            ]);
        });
    }

    /**
     * Log de edición de cabecera (mantener como lo tenías).
     */
    public function updated(OrdenCompra $oc): void
    {
        $watch = [
            'numero_orden','fecha','solicitante_id','proveedor_id','descripcion',
            'iva_porcentaje','subtotal','iva_monto','monto','notas','estado','factura',
        ];

        $changes = [];
        foreach ($watch as $field) {
            $old = $oc->getOriginal($field);
            $new = $oc->getAttribute($field);

            if ($field === 'fecha') {
                try {
                    if ($old) $old = Carbon::parse($old)->format('Y-m-d');
                    if ($new) $new = Carbon::parse($new)->format('Y-m-d');
                } catch (\Throwable $e) {}
            }

            if ($this->norm($field, $old) !== $this->norm($field, $new)) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }

        if (!$changes) return;

        foreach ($changes as $campo => &$chg) {
            if ($campo === 'solicitante_id') {
                $oldColab = \App\Models\Colaborador::find($chg['from'] ?? null);
                $newColab = \App\Models\Colaborador::find($chg['to'] ?? null);
                $oldName  = $oldColab ? trim(($oldColab->nombre ?? '').' '.($oldColab->apellidos ?? '')) : null;
                $newName  = $newColab ? trim(($newColab->nombre ?? '').' '.($newColab->apellidos ?? '')) : null;
                if ($oldName || $newName) {
                    $chg['from'] = isset($chg['from']) ? trim($chg['from'].' - '.$oldName) : $oldName;
                    $chg['to']   = isset($chg['to'])   ? trim($chg['to'].' - '.$newName) : $newName;
                }
            }

            if ($campo === 'proveedor_id') {
                $oldProv = \App\Models\Proveedor::find($chg['from'] ?? null);
                $newProv = \App\Models\Proveedor::find($chg['to'] ?? null);
                $oldName = $oldProv?->nombre;
                $newName = $newProv?->nombre;
                if ($oldName || $newName) {
                    $chg['from'] = isset($chg['from']) ? trim($chg['from'].' - '.$oldName) : $oldName;
                    $chg['to']   = isset($chg['to'])   ? trim($chg['to'].' - '.$newName) : $newName;
                }
            }
        }
        unset($chg);

        if (array_key_exists('estado', $changes)) {
            $type = 'state_changed';
            $data = [
                'from' => $changes['estado']['from'] ?? null,
                'to'   => $changes['estado']['to']   ?? null,
            ];
        } else {
            $type = 'updated';
            $data = $changes;
        }

        $fingerprint = $oc->id.'|'.$type.'|'.md5(json_encode($data, JSON_UNESCAPED_UNICODE));
        if (isset(self::$seen[$fingerprint])) return;
        self::$seen[$fingerprint] = true;

        $dup = OcLog::where('orden_compra_id', $oc->id)
            ->where('type', $type)
            ->where('user_id', Auth::id())
            ->where('created_at', '>=', now()->subSeconds(2))
            ->get()
            ->first(function ($log) use ($data) {
                return md5(json_encode($log->data, JSON_UNESCAPED_UNICODE))
                     === md5(json_encode($data, JSON_UNESCAPED_UNICODE));
            });
        if ($dup) return;

        OcLog::create([
            'orden_compra_id' => $oc->id,
            'user_id'         => Auth::id(),
            'type'            => $type,
            'data'            => $data,
        ]);
    }
}
