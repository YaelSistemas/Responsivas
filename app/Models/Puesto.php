<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Puesto extends Model
{
    use HasFactory;

    protected $table = 'puestos';

    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'created_by',
        'nombre',
        'descripcion',
    ];

    /* -------- Scopes -------- */
    public function scopeDeEmpresa($q, int $tenantId)
    {
        return $q->where('empresa_tenant_id', $tenantId);
    }

    /* -------- Blindaje por tenant en rutas /puestos/{puesto} -------- */
    public function resolveRouteBinding($value, $field = null)
    {
        $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id);

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('empresa_tenant_id', $tenant)
            ->firstOrFail();
    }

    /* -------- Asignaciones automáticas e historial -------- */
    protected static function booted()
    {
        // 🔹 Asignaciones automáticas
        static::creating(function (self $m) {
            $user   = auth()->user();
            $tenant = (int) session('empresa_activa', $user?->empresa_id);

            // Tenant y auditoría
            $m->empresa_tenant_id ??= $tenant;
            $m->created_by        ??= $user?->id;

            // Folio consecutivo por tenant
            if (empty($m->folio) && !empty($m->empresa_tenant_id)) {
                $max = static::where('empresa_tenant_id', $m->empresa_tenant_id)->max('folio');
                $m->folio = ($max ?? 0) + 1;
            }
        });

        // 🔹 Historial de creación
        static::created(function ($puesto) {
            \App\Models\PuestoHistorial::create([
                'puesto_id' => $puesto->id,
                'user_id'   => auth()->id(),
                'accion'    => 'Creación',
                'cambios'   => [
                    'nombre'       => $puesto->nombre,
                    'descripcion'  => $puesto->descripcion,
                ],
            ]);
        });

        // 🔹 Historial de actualización
        static::updated(function ($puesto) {
            $cambios = [];
            foreach ($puesto->getChanges() as $campo => $nuevoValor) {
                if (in_array($campo, ['updated_at'])) continue; // ignorar timestamps
                $original = $puesto->getOriginal($campo);
                $cambios[$campo] = ['de' => $original, 'a' => $nuevoValor];
            }

            if (!empty($cambios)) {
                \App\Models\PuestoHistorial::create([
                    'puesto_id' => $puesto->id,
                    'user_id'   => auth()->id(),
                    'accion'    => 'Actualización',
                    'cambios'   => $cambios,
                ]);
            }
        });
    }

}
