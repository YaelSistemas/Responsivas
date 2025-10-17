<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'notas',
        'monto',
        'factura',
        'estado',
        'created_by','updated_by',
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

    protected static function booted()
    {
        static::creating(function ($oc) {
            if (auth()->check()) $oc->created_by = $oc->created_by ?: auth()->id();
        });

        static::updating(function ($oc) {
            if (auth()->check()) $oc->updated_by = auth()->id();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public const EST_ABIERTA   = 'abierta';
    public const EST_PAGADA    = 'pagada';
    public const EST_CANCELADA = 'cancelada';

    public const ESTADOS = [
        self::EST_ABIERTA,
        self::EST_PAGADA,
        self::EST_CANCELADA,
    ];

    protected $attributes = [
        'estado' => self::EST_ABIERTA,
    ];

    public function getEstadoLabelAttribute(): string
    {
        return match($this->estado) {
            self::EST_PAGADA    => 'Pagada',
            self::EST_CANCELADA => 'Cancelada',
            default             => 'Abierta',
        };
    }

    public function getEstadoClassAttribute(): string
    {
        // Usamos clases "tag-*" que definimos en CSS
        return match($this->estado) {
            self::EST_PAGADA    => 'tag-green',
            self::EST_CANCELADA => 'tag-red',
            default             => 'tag-blue',
        };
    }

    public function adjuntos()
    {
        return $this->hasMany(\App\Models\OcAdjunto::class, 'orden_compra_id');
    }

    public function logs()
    {
        return $this->hasMany(\App\Models\OcLog::class, 'orden_compra_id')->latest();
    }

}
