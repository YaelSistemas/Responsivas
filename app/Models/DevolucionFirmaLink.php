<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DevolucionFirmaLink extends Model
{
    protected $table = 'devolucion_firma_links'; // o el nombre que uses

    protected $fillable = [
        'devolucion_id',
        'token',
        'campo',       // si lo usas para ENTREGÓ / PSITIO
        'expires_at',
        'signed_at',
    ];

    // 👈 MUY IMPORTANTE: CASTS A DATETIME
    protected $casts = [
        'expires_at' => 'datetime',
        'signed_at'  => 'datetime',
    ];

    public function devolucion()
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        // Garantizamos que sea Carbon por si viene como string
        $expires = $this->expires_at instanceof Carbon
            ? $this->expires_at
            : Carbon::parse($this->expires_at);

        return $expires->isPast();
    }

    public function isSigned(): bool
    {
        return !is_null($this->signed_at);
    }
}
