<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraDetalle extends Model
{
    protected $table = 'orden_compra_detalles';

    protected $fillable = [
        'orden_compra_id',
        'cantidad','unidad','concepto',
        'moneda','precio','importe',
        'iva_pct','isr_pct','isr_monto',
        'iva_monto','ret_iva_pct','ret_iva_monto',
        'subtotal','total',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }
}
