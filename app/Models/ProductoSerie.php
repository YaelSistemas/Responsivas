<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Responsiva;
use App\Models\ProductoSerieHistorial;
use App\Models\Subsidiaria;
use App\Models\UnidadServicio;

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
        'subsidiaria_id',
        'unidad_servicio_id',
        'especificaciones',               // <-- overrides por serie (JSON)
    ];

    protected $casts = [
        'empresa_tenant_id'         => 'integer',
        'producto_id'               => 'integer',
        'asignado_en_responsiva_id' => 'integer',
        'subsidiaria_id'            => 'integer',
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

    public function subsidiaria()
    {
        return $this->belongsTo(Subsidiaria::class, 'subsidiaria_id');
    }

    public function unidadServicio()
    {
        return $this->belongsTo(UnidadServicio::class, 'unidad_servicio_id');
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

    public function fotos() 
    {
        return $this->hasMany(\App\Models\ProductoSerieFoto::class, 'producto_serie_id');
    }

    // Relación
    public function historial()
    {
        return $this->hasMany(ProductoSerieHistorial::class, 'producto_serie_id')
                    ->orderByDesc('created_at');
    }

    /**
 * Registrar un movimiento en el kardex de esta serie.
 */
public function registrarHistorial(array $extra = []): void
{
    $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id);

    // 1) Intentamos sacar el producto_id del propio modelo o de la relación
    $productoId = $this->producto_id ?? $this->producto?->id ?? null;

    // 2) Si aun así viene null (porque se cargó la serie con un select limitado),
    //    lo leemos directo de la base de datos como último recurso.
    if (!$productoId) {
        $productoId = \DB::table('producto_series')
            ->where('id', $this->id)
            ->value('producto_id');
    }

    // 3) Valores por defecto que NO deben poder ser pisados
    $defaults = [
        'empresa_tenant_id' => $tenant,
        'producto_serie_id' => $this->id,
        'producto_id'       => $productoId,
        'user_id'           => auth()->id(),
    ];

    // 4) Usamos el operador + para que $extra NO sobrescriba estos campos
    $data = $defaults + $extra;

    \App\Models\ProductoSerieHistorial::create($data);
}


}
