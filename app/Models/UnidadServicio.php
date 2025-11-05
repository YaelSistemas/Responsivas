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

        // 🟢 CREACIÓN
        static::created(function (self $unidad) {
            \App\Models\UnidadServicioHistorial::create([
                'unidad_id' => $unidad->id,
                'user_id'            => auth()->id(),
                'accion'             => 'Creación',
                'cambios'            => self::mapNames([
                    'nombre'        => $unidad->nombre,
                    'direccion'     => $unidad->direccion,
                    'responsable_id'=> $unidad->responsable_id,
                ]),
            ]);
        });

        // 🔵 ACTUALIZACIÓN
        static::updated(function (self $unidad) {
            $original = $unidad->getOriginal();
            $cambios = [];

            foreach ($unidad->getChanges() as $campo => $nuevoValor) {
                if (in_array($campo, ['updated_at', 'created_at'])) continue;

                $anterior = $original[$campo] ?? null;
                if ($anterior != $nuevoValor) {
                    $cambios[$campo] = [
                        'de' => $anterior,
                        'a'  => $nuevoValor,
                    ];
                }
            }

            if (!empty($cambios)) {
                $cambios = self::mapNames($cambios);
                \App\Models\UnidadServicioHistorial::create([
                    'unidad_id' => $unidad->id,
                    'user_id'            => auth()->id(),
                    'accion'             => 'Actualización',
                    'cambios'            => $cambios,
                ]);
            }
        });
    }

    protected static function mapNames(array $cambios): array
    {
        $map = [
            'responsable_id' => \App\Models\Colaborador::pluck('nombre', 'id')->toArray(),
        ];

        foreach ($cambios as $campo => &$valor) {
            if (isset($map[$campo])) {
                if (is_array($valor)) {
                    $valor['de'] = $map[$campo][$valor['de']] ?? $valor['de'];
                    $valor['a']  = $map[$campo][$valor['a']]  ?? $valor['a'];
                } else {
                    $valor = $map[$campo][$valor] ?? $valor;
                }
            }
        }

        return $cambios;
    }


}
