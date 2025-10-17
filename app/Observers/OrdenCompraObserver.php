<?php

namespace App\Observers;

use App\Models\OrdenCompra;
use App\Models\OcLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class OrdenCompraObserver
{
    protected static array $seen = [];

    /** Normaliza el valor por campo para evitar falsos positivos (string "1" vs int 1, etc.) */
    protected function norm(string $field, $value)
    {
        if ($value === null) return null;

        // IDs foráneos
        if (in_array($field, ['solicitante_id', 'proveedor_id'], true)) {
            return (string) (int) $value; // "1"
        }

        // Numéricos/monetarios
        if (in_array($field, ['iva_porcentaje','subtotal','iva_monto','monto'], true)) {
            return (string) (float) $value; // "123.45"
        }

        // Fecha ya la formateamos aparte a Y-m-d
        if ($field === 'fecha') {
            return (string) $value;
        }

        // Resto: comparar como string
        return (string) $value;
    }

    public function created(OrdenCompra $oc): void
    {
        // intencionalmente vacío -> registramos la creación en saved()
    }

    public function saved(OrdenCompra $oc): void
    {
        if (!$oc->wasRecentlyCreated) return;

        if (method_exists($oc, 'solicitante')) $oc->loadMissing('solicitante');
        if (method_exists($oc, 'proveedor'))   $oc->loadMissing('proveedor');
        if (method_exists($oc, 'detalles'))    $oc->loadMissing('detalles');

        $solName = $oc->solicitante
            ? trim(($oc->solicitante->nombre ?? '').' '.($oc->solicitante->apellidos ?? ''))
            : null;

        $payload = [
            'numero_orden'   => $oc->numero_orden,
            'fecha'          => $oc->fecha ? Carbon::parse($oc->fecha)->format('Y-m-d') : null,
            'solicitante'    => $solName ?? ($oc->solicitante?->name ?? $oc->solicitante_id),
            'proveedor'      => $oc->proveedor?->nombre ?? $oc->proveedor_id,
            'descripcion'    => $oc->descripcion,
            'iva_porcentaje' => Schema::hasColumn($oc->getTable(),'iva_porcentaje') ? $oc->iva_porcentaje : null,
            'subtotal'       => Schema::hasColumn($oc->getTable(),'subtotal')      ? $oc->subtotal      : null,
            'iva'            => Schema::hasColumn($oc->getTable(),'iva_monto')     ? $oc->iva_monto     : null,
            'total'          => $oc->monto ?? ($oc->total ?? null),
            'notas'          => Schema::hasColumn($oc->getTable(),'notas')         ? $oc->notas         : null,
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

        OcLog::create([
            'orden_compra_id' => $oc->id,
            'user_id'         => Auth::id(),
            'type'            => 'created',
            'data'            => $payload,
        ]);
    }

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

            // ⇩⇩ Comparación con valores normalizados para evitar "1" vs 1, "12.0" vs 12 etc.
            if ($this->norm($field, $old) !== $this->norm($field, $new)) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }

        if (!$changes) return;

        // Traducir IDs a "id - nombre"
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
            $data = $changes; // sin wrapper "changes"
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
