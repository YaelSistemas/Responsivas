<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Subsidiaria;          // subsidiaria
use App\Models\UnidadServicio;
use App\Models\Area;
use App\Models\Puesto;
use App\Models\ColaboradorHistorial;
use Illuminate\Support\Facades\Auth;

class Colaborador extends Model
{
    use HasFactory;

    // 👇 si tu tabla se llama 'colaboradores'
    protected $table = 'colaboradores';

    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'created_by',
        'nombre',
        'apellidos',
        // FKs (reemplazan a los textos anteriores)
        'subsidiaria_id',       // antes "empresa"
        'unidad_servicio_id',
        'area_id',
        'puesto_id',
        'activo',
    ];

    /* ============================
     |  Relaciones
     ============================ */
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Empresa "subsidiaria" (usa tu tabla empresas)
    public function subsidiaria()
    {
        return $this->belongsTo(Subsidiaria::class, 'subsidiaria_id');
    }

    public function unidadServicio()
    {
        return $this->belongsTo(UnidadServicio::class, 'unidad_servicio_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function puesto()
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }

    /* ============================
     |  Scopes y tenant routing
     ============================ */
    public function scopeDeEmpresa($query, int $tenantId)
    {
        return $query->where('empresa_tenant_id', $tenantId);
    }

    // 🔒 Evita acceder a registros de otro tenant por URL
    public function resolveRouteBinding($value, $field = null)
    {
        $tenant = session('empresa_activa', auth()->user()?->empresa_id);
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('empresa_tenant_id', $tenant)
            ->firstOrFail();
    }

    /* ============================
     |  Asignaciones automáticas
     ============================ */
    protected static function booted()
    {
        static::creating(function (self $model) {
            $user = auth()->user();

            // Tenant y auditoría
            $model->empresa_tenant_id = $model->empresa_tenant_id ?: ($user->empresa_id ?? null);
            $model->created_by        = $model->created_by ?: ($user?->id);

            // Folio consecutivo por tenant
            if (empty($model->folio) && !empty($model->empresa_tenant_id)) {
                $max = static::where('empresa_tenant_id', $model->empresa_tenant_id)->max('folio');
                $model->folio = ($max ?? 0) + 1;
            }
        });

        // 🟢 CREACIÓN
        static::created(function (self $colaborador) {
            \App\Models\ColaboradorHistorial::create([
                'colaborador_id' => $colaborador->id,
                'user_id'        => auth()->id(),
                'accion'         => 'Creación',
                'cambios'        => self::mapNames([
                    'nombre'             => $colaborador->nombre,
                    'apellidos'          => $colaborador->apellidos,
                    'subsidiaria_id'     => $colaborador->subsidiaria_id,
                    'unidad_servicio_id' => $colaborador->unidad_servicio_id,
                    'area_id'            => $colaborador->area_id,
                    'puesto_id'          => $colaborador->puesto_id,
                ]),
            ]);
        });

        // 🔵 ACTUALIZACIÓN
        static::updated(function (self $colaborador) {
            $original = $colaborador->getOriginal();
            $cambios = [];

            foreach ($colaborador->getChanges() as $campo => $nuevoValor) {
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
                // 🔹 Traducir IDs a nombres
                $cambios = self::mapNames($cambios);

                \App\Models\ColaboradorHistorial::create([
                    'colaborador_id' => $colaborador->id,
                    'user_id'        => auth()->id(),
                    'accion'         => 'Actualización',
                    'cambios'        => $cambios,
                ]);
            }
        });
    }

    /**
    * 🔄 Convierte los IDs de relaciones en nombres legibles
    */
    protected static function mapNames(array $cambios): array
    {
        $map = [
            'subsidiaria_id'     => Subsidiaria::pluck('nombre', 'id')->toArray(),
            'unidad_servicio_id' => UnidadServicio::pluck('nombre', 'id')->toArray(),
            'area_id'            => Area::pluck('nombre', 'id')->toArray(),
            'puesto_id'          => Puesto::pluck('nombre', 'id')->toArray(),
        ];

        foreach ($cambios as $campo => &$valor) {
            if (isset($map[$campo])) {
                // Si el valor es array (tiene 'de' y 'a')
                if (is_array($valor)) {
                    $valor['de'] = $map[$campo][$valor['de']] ?? $valor['de'];
                    $valor['a']  = $map[$campo][$valor['a']]  ?? $valor['a'];
                } else {
                    // Si es creación (valor plano)
                    $valor = $map[$campo][$valor] ?? $valor;
                }
            }
        }

        return $cambios;
    }

    protected $appends = ['nombre_completo'];
    
    public function getNombreCompletoAttribute(): string
    {
        $nom = trim((string) $this->nombre);
        $ape = trim((string) $this->apellidos);
        return trim($nom . ' ' . $ape);
    }

}
