<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\AreaHistorial;
use App\Models\User;

class Area extends Model
{
    use HasFactory;

    protected $table = 'areas';

    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'created_by',
        'nombre',
        'descripcion',
    ];

    /* ---------- Scopes ---------- */
    public function scopeDeEmpresa($q, int $tenantId)
    {
        return $q->where('empresa_tenant_id', $tenantId);
    }

    /* ---------- Blindaje por tenant (rutas /areas/{area}) ---------- */
    public function resolveRouteBinding($value, $field = null)
    {
        $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id);

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('empresa_tenant_id', $tenant)
            ->firstOrFail();
    }

    /* ---------- Asignaciones automáticas ---------- */
    protected static function booted()
    {
        // ====== BLOQUE ORIGINAL (NO TOCAR) ======
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

        // ====== NUEVO BLOQUE: REGISTRO DE HISTORIAL ======
        static::created(function (self $area) {
            AreaHistorial::create([
                'area_id' => $area->id,
                'user_id' => auth()->id(),
                'accion'  => 'Creación',
                'cambios' => [
                    'nombre'      => $area->nombre,
                    'descripcion' => $area->descripcion,
                ],
            ]);
        });

        static::updated(function (self $area) {
            $original = $area->getOriginal();
            $cambios = [];

            foreach ($area->getChanges() as $campo => $nuevo) {
                if (in_array($campo, ['updated_at', 'created_at'])) continue;
                $anterior = $original[$campo] ?? null;
                if ($anterior != $nuevo) {
                    $cambios[$campo] = ['de' => $anterior, 'a' => $nuevo];
                }
            }

            if (!empty($cambios)) {
                AreaHistorial::create([
                    'area_id' => $area->id,
                    'user_id' => auth()->id(),
                    'accion'  => 'Actualización',
                    'cambios' => $cambios,
                ]);
            }
        });
    }
}
