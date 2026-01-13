<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cartucho extends Model
{
    protected $table = 'cartuchos';

    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'fecha_solicitud',
        'colaborador_id',
        'producto_id',
        'realizado_por',
        'firma_realizo',
        'firma_recibio',

        // (firma real)
        'firma_colaborador_path',
        'firma_colaborador_at',

        // (firma por link)
        'firma_token',
        'firma_token_expires_at',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'firma_colaborador_at' => 'datetime',
        'firma_token_expires_at' => 'datetime',
    ];

    /* =========================
     | Scopes
     ========================= */
    public function scopeDeEmpresa($query, $tenantId)
    {
        return $query->where('empresa_tenant_id', $tenantId);
    }

    /* =========================
     | Relaciones
     ========================= */
    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_id');
    }

    // Equipo (impresora/multifuncional)
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    // Usuario que registró (campo: realizado_por)
    public function realizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realizado_por');
    }

    // Firma realizó (usuario admin) (campo: firma_realizo)
    public function firmaRealizoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firma_realizo');
    }

    // Firma recibió (colaborador) (campo: firma_recibio)
    public function firmaRecibioColaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'firma_recibio');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CartuchoDetalle::class, 'cartucho_id');
    }

    /* =========================
     | Helpers (opcionales)
     ========================= */
    public function tieneFirmaRealizo(): bool
    {
        return !empty($this->firma_realizo);
    }

    public function tieneFirmaRecibio(): bool
    {
        return !empty($this->firma_recibio);
    }
}
