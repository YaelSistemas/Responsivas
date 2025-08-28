<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class ProductoSerie extends Model
{
    use HasFactory;

    protected $table = 'producto_series';

    // Estados recomendados
    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_ASIGNADO   = 'asignado';
    public const ESTADO_DEVUELTO   = 'devuelto';
    public const ESTADO_BAJA       = 'baja';

    protected $fillable = [
        'empresa_tenant_id',
        'producto_id',
        'serie',
        'estado',
        'ubicacion',
        'observaciones',
        'asignado_en_responsiva_id',
        'especificaciones',               // <-- overrides por serie (JSON)
    ];

    protected $casts = [
        'empresa_tenant_id'         => 'integer',
        'producto_id'               => 'integer',
        'asignado_en_responsiva_id' => 'integer',
        'especificaciones'          => 'array',   // <-- importante
        // si creaste columnas generadas, puedes castear también:
        // 'ram_gb_index'            => 'integer',
        // 'alm_tipo_index'          => 'string',
    ];

    /* =================== Relaciones =================== */

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    // Responsiva donde quedó asignada esta serie (si llevas el rastro)
    public function responsivaAsignada()
    {
        return $this->belongsTo(Responsiva::class, 'asignado_en_responsiva_id');
    }

    /* =================== Scopes =================== */

    public function scopeDeEmpresa(Builder $q, int $tenantId): Builder
    {
        return $q->where('empresa_tenant_id', $tenantId);
    }

    public function scopeDisponibles(Builder $q): Builder
    {
        return $q->where('estado', self::ESTADO_DISPONIBLE);
    }

    public function scopeAsignadas(Builder $q): Builder
    {
        return $q->where('estado', self::ESTADO_ASIGNADO);
    }

    /* =================== Helpers de especificaciones =================== */

    /**
     * Especificaciones efectivas = specs del producto base
     * + overrides guardados en la serie (especificaciones).
     */
    public function getSpecsAttribute(): array
    {
        $base = (array) ($this->producto?->especificaciones ?? []);
        $over = (array) ($this->especificaciones ?? []);
        // mezcla recursiva, la serie sobreescribe al producto
        return array_replace_recursive($base, $over);
    }

    // Atajos de lectura (usables en blades: $serie->ram_gb, $serie->alm_tipo, etc.)
    public function getColorAttribute()          { return data_get($this->specs, 'color'); }
    public function getRamGbAttribute()          { return data_get($this->specs, 'ram_gb'); }
    public function getAlmTipoAttribute()        { return data_get($this->specs, 'almacenamiento.tipo'); }
    public function getAlmCapacidadGbAttribute() { return data_get($this->specs, 'almacenamiento.capacidad_gb'); }
    public function getCpuAttribute()            { return data_get($this->specs, 'procesador'); }

    /* =================== Multi-tenant & defaults =================== */

    protected static function booted()
    {
        static::creating(function (self $m) {
            $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id);
            $m->empresa_tenant_id ??= $tenant;
            $m->estado = $m->estado ?: self::ESTADO_DISPONIBLE;
        });
    }
}
