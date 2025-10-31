<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Responsiva extends Model
{
    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'colaborador_id',
        'user_id',
        'fecha_solicitud',
        'fecha_entrega',
        'observaciones',
        'motivo_entrega',
        'recibi_colaborador_id',
        'autoriza_user_id',

        // firma pública
        'sign_token','sign_token_expires_at',
        'firma_colaborador_path','firmado_por','firmado_en','firmado_ip','signed_at',
    ];

    protected $casts = [
        'fecha_solicitud'        => 'date',
        'fecha_entrega'          => 'date',
        'sign_token_expires_at'  => 'datetime',
        'firmado_en'             => 'datetime',
    ];

    // === Helpers ===
    public function getFirmaColaboradorUrlAttribute(): ?string
    {
        $p = $this->firma_colaborador_path;
        if (!$p) return null;

        // Si ya es URL absoluta
        if (preg_match('~^https?://~i', $p)) {
            return $p;
        }

        // 1) intenta en el disco 'public' (storage/app/public)
        if (Storage::disk('public')->exists($p)) {
            return Storage::url($p); // => /storage/firmas_colaboradores/...
        }

        // 2) intenta en public/ directo (por si guardaste ahí)
        if (file_exists(public_path($p))) {
            return asset($p);
        }

        return null;
    }

    /* --------------------  Multi-tenant (igual que ya tenías) -------------------- */
    protected static function booted(): void
    {
        static::addGlobalScope('empresa', function (Builder $q) {
            $empresaId = (int) session('empresa_activa', auth()->user()?->empresa_id);
            if ($empresaId) $q->where('empresa_tenant_id', $empresaId);
        });

        static::creating(function (self $model) {
            if (empty($model->empresa_tenant_id)) {
                $model->empresa_tenant_id = (int) session('empresa_activa', auth()->user()?->empresa_id);
            }
            if (empty($model->folio)) {
                $model->folio = self::makeNextFolio($model->empresa_tenant_id);
            }
        });
    }

    public static function makeNextFolio(int $empresaId): string
    {
        $year   = now()->format('Y');
        $prefix = "R-{$year}-";

        $lastFolio = static::withoutGlobalScopes()
            ->where('empresa_tenant_id', $empresaId)
            ->where('folio', 'like', $prefix.'%')
            ->orderByDesc('id')->value('folio');

        $seq = 1;
        if ($lastFolio && preg_match('/^R-\d{4}-(\d+)$/', $lastFolio, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    // Relaciones (igual que tenías)...
    public function entrego()    { return $this->belongsTo(User::class, 'user_id'); }
    public function autoriza()   { return $this->belongsTo(User::class, 'autoriza_user_id'); }
    public function recibi()     { return $this->belongsTo(Colaborador::class, 'recibi_colaborador_id'); }
    public function colaborador(){ return $this->belongsTo(Colaborador::class); }
    public function usuario()    { return $this->belongsTo(User::class,'user_id'); }
    public function detalles()
    {
        return $this->hasMany(\App\Models\ResponsivaDetalle::class, 'responsiva_id', 'id');
    }

    public function scopeForEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->withoutGlobalScope('empresa')->where('empresa_tenant_id', $empresaId);
    }
}
