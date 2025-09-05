<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Responsiva extends Model
{
    protected $fillable = [
        'empresa_tenant_id',      // <- importante
        'folio',
        'colaborador_id',
        'user_id',
        'fecha_entrega',
        'observaciones',
        'motivo_entrega',
        'recibi_colaborador_id',
        'autoriza_user_id',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
    'fecha_entrega'   => 'date',
    ];

    /* --------------------  Multi-tenant  -------------------- */
    protected static function booted(): void
    {
        // Global scope por empresa activa (no comparte datos entre empresas)
        static::addGlobalScope('empresa', function (Builder $q) {
            $empresaId = (int) session('empresa_activa', auth()->user()?->empresa_id);
            if ($empresaId) {
                $q->where('empresa_tenant_id', $empresaId);
            }
        });

        // Al crear, setear empresa y folio (si no vienen)
        static::creating(function (self $model) {
            if (empty($model->empresa_tenant_id)) {
                $model->empresa_tenant_id = (int) session('empresa_activa', auth()->user()?->empresa_id);
            }
            if (empty($model->folio)) {
                $model->folio = self::makeNextFolio($model->empresa_tenant_id);
            }
        });
    }

    /**
     * Genera el siguiente folio para la empresa dada.
     * Formato: R-YYYY-#### (secuencial por empresa y año)
     */
    public static function makeNextFolio(int $empresaId): string
    {
        $year   = now()->format('Y');
        $prefix = "R-{$year}-";

        // Busca el último folio de esa empresa en el año
        $lastFolio = static::withoutGlobalScopes()
            ->where('empresa_tenant_id', $empresaId)
            ->where('folio', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('folio');

        $seq = 1;
        if ($lastFolio && preg_match('/^R-\d{4}-(\d+)$/', $lastFolio, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /* --------------------  Relaciones  -------------------- */
    public function entrego()    { return $this->belongsTo(User::class, 'user_id'); }
    public function autoriza()   { return $this->belongsTo(User::class, 'autoriza_user_id'); }
    public function recibi()     { return $this->belongsTo(Colaborador::class, 'recibi_colaborador_id'); }
    public function colaborador(){ return $this->belongsTo(Colaborador::class); }
    public function usuario()    { return $this->belongsTo(User::class,'user_id'); }
    public function detalles()   { return $this->hasMany(ResponsivaDetalle::class); }

    /* --------------------  Scopes útiles  -------------------- */
    // Por si alguna vez quieres filtrar manualmente por otra empresa:
    public function scopeForEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->withoutGlobalScope('empresa')->where('empresa_tenant_id', $empresaId);
    }
}
