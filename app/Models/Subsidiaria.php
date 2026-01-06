<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ProductoSerie;
use Illuminate\Database\Eloquent\Builder;

class Subsidiaria extends Model
{
    use HasFactory;

    protected $table = 'subsidiarias';

    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'created_by',
        'nombre',
        'descripcion',
    ];

    public function productoSeries()
    {
        return $this->hasMany(ProductoSerie::class, 'subsidiaria_id');
    }

    /* -------- Scopes -------- */
    public function scopeDeEmpresa(Builder $q, int $tenantId): Builder
    {
        return $q->where('empresa_tenant_id', $tenantId);
    }

    /* -------- Blindaje por tenant en rutas /subsidiarias/{subsidiaria} -------- */
    public function resolveRouteBinding($value, $field = null)
    {
        $user   = auth()->user();
        $tenant = (int) session('empresa_activa', $user?->empresa_id);

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->when($tenant, fn($q) => $q->where('empresa_tenant_id', $tenant))
            ->firstOrFail();
    }

    /* -------- Asignaciones automáticas + Historial -------- */
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

        // 🔹 Historial: creación
        static::created(function (self $m) {
            \App\Models\SubsidiariaHistorial::create([
                'subsidiaria_id' => $m->id,
                'user_id'        => auth()->id(),
                'accion'         => 'Creación',
                'cambios'        => [
                    'nombre' => $m->nombre,
                    'descripcion' => $m->descripcion,
                ],
            ]);
        });

        // 🔹 Historial: actualización (con formato {de, a})
        static::updated(function (self $m) {
            $original = $m->getOriginal();
            $changes  = [];

            foreach ($m->getDirty() as $campo => $nuevoValor) {
                // Solo registrar campos importantes
                if (in_array($campo, ['nombre', 'descripcion'])) {
                    $valorAnterior = $original[$campo] ?? null;
                    $changes[$campo] = [
                        'de' => $valorAnterior,
                        'a'  => $nuevoValor,
                    ];
                }
            }

            if (!empty($changes)) {
                \App\Models\SubsidiariaHistorial::create([
                    'subsidiaria_id' => $m->id,
                    'user_id'        => auth()->id(),
                    'accion'         => 'Actualización',
                    'cambios'        => $changes,
                ]);
            }
        });

        // 🔹 Historial: eliminación (antes del delete, para evitar error FK)
        static::deleting(function (self $m) {
            \App\Models\SubsidiariaHistorial::create([
                'subsidiaria_id' => $m->id,
                'user_id'        => auth()->id(),
                'accion'         => 'Eliminación',
                'cambios'        => [
                    'nombre'      => $m->nombre,
                    'descripcion' => $m->descripcion,
                ],
            ]);
        });
    }

}
