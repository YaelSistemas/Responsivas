<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    protected $fillable = [
        'empresa_tenant_id',
        'numero_orden',
        'fecha',
        'solicitante_id',
        'proveedor_id',
        'descripcion',
        'monto',
        'factura',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_tenant_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(Colaborador::class, 'solicitante_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function detalles()
    {
    return $this->hasMany(\App\Models\OrdenCompraDetalle::class, 'orden_compra_id');
    }
}
