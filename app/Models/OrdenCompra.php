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
        'recepcion',   // ✅ NUEVO
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    /* =====================
       RELACIONES
    ======================*/

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

    public function adjuntos()
    {
        return $this->hasMany(\App\Models\OcAdjunto::class, 'orden_compra_id');
    }

    public function logs()
    {
        return $this->hasMany(\App\Models\OcLog::class, 'orden_compra_id')->latest();
    }

    /* =====================
       CONTROL DE USUARIO
    ======================*/

    protected static function booted()
    {
        static::creating(function ($oc) {
            if (auth()->check()) {
                $oc->created_by = $oc->created_by ?: auth()->id();
            }
        });

        static::updating(function ($oc) {
            if (auth()->check()) {
                $oc->updated_by = auth()->id();
            }
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

    /* =====================
       ESTADOS
    ======================*/

    public const EST_ABIERTA   = 'abierta';
    public const EST_PAGADA    = 'pagada';
    public const EST_CANCELADA = 'cancelada';

    public const ESTADOS = [
        self::EST_ABIERTA,
        self::EST_PAGADA,
        self::EST_CANCELADA,
    ];

    /* =====================
       RECEPCIÓN
    ======================*/

    public const REC_SIN_RECEPCION = 'sin_recepcion';
    public const REC_RECIBIDO      = 'recibido';

    public const RECEPCIONES = [
        self::REC_SIN_RECEPCION,
        self::REC_RECIBIDO,
    ];

    /* =====================
       DEFAULT ATTRIBUTES
    ======================*/

    protected $attributes = [
        'estado'    => self::EST_ABIERTA,
        'recepcion' => self::REC_SIN_RECEPCION,  // ✅ NUEVO DEFAULT
    ];

    /* =====================
       ACCESSORS (LABELS)
    ======================*/

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::EST_PAGADA    => 'Pagada',
            self::EST_CANCELADA => 'Cancelada',
            default             => 'Abierta',
        };
    }

    public function getEstadoClassAttribute(): string
    {
        return match ($this->estado) {
            self::EST_PAGADA    => 'tag-green',
            self::EST_CANCELADA => 'tag-red',
            default             => 'tag-blue',
        };
    }

    public function getRecepcionLabelAttribute(): string
    {
        return match ($this->recepcion) {
            self::REC_RECIBIDO  => 'Recibido',
            default             => 'Sin recepcion',
        };
    }

    public function getRecepcionClassAttribute(): string
    {
        return match ($this->recepcion) {
            self::REC_RECIBIDO  => 'tag-green',
            default             => 'tag-gray',
        };
    }
}
