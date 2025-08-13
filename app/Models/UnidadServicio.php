<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnidadServicio extends Model
{
    use HasFactory;

    protected $table = 'unidades_servicio';

    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'created_by',
        'nombre',
        'direccion',       // <- reemplaza 'descripcion'
        'responsable_id',  // <- nuevo
    ];

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Responsable (colaborador)
    public function responsable()
    {
        return $this->belongsTo(Colaborador::class, 'responsable_id');
    }

    /* Filtrar por tenant */
    public function scopeDeEmpresa($query, int $tenantId)
    {
        return $query->where('empresa_tenant_id', $tenantId);
    }

    /* Completar tenant, created_by y folio automáticamente */
    protected static function booted()
    {
        static::creating(function (self $m) {
            $user   = auth()->user();
            $tenant = (int) session('empresa_activa', $user?->empresa_id);

            $m->empresa_tenant_id ??= $tenant;
            $m->created_by        ??= $user?->id;

            if (empty($m->folio) && $m->empresa_tenant_id) {
                $m->folio = (static::where('empresa_tenant_id', $m->empresa_tenant_id)->max('folio') ?? 0) + 1;
            }
        });
    }
}
