<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    // SOLO las columnas reales que existen en la tabla
    protected $fillable = [
        'empresa_tenant_id','folio','created_by',
        'nombre','sku','marca','modelo','descripcion',
        'tipo','es_serializado','unidad','especificaciones','activo',
    ];

    protected $casts = [
        'activo'           => 'boolean',
        'especificaciones' => 'array',
    ];

    /* ===== Compatibilidad: "tracking" / "unidad_medida" virtuales ===== */

    // Leer tracking (derivado de es_serializado)
    public function getTrackingAttribute()
    {
        return ($this->attributes['es_serializado'] ?? 0) ? 'serial' : 'cantidad';
    }

    // Al escribir tracking, solo mapea a es_serializado (NO guardes "tracking")
    public function setTrackingAttribute($value)
    {
        $this->attributes['es_serializado'] = ($value === 'serial') ? 1 : 0;
        // OJO: No guardar $this->attributes['tracking'] para evitar que intente persistir esa columna
    }

    // Leer unidad_medida desde "unidad"
    public function getUnidadMedidaAttribute()
    {
        return $this->attributes['unidad'] ?? null;
    }

    // Al escribir unidad_medida, mapea a "unidad"
    public function setUnidadMedidaAttribute($value)
    {
        $this->attributes['unidad'] = $value;
        // No escribir 'unidad_medida' para no persistir una columna que no existe
    }

    /* ===== Scopes & Relaciones ===== */

    public function scopeDeEmpresa($q, int $tenantId){ return $q->where('empresa_tenant_id', $tenantId); }

    public function creador(){ return $this->belongsTo(User::class, 'created_by'); }
    public function series(){ return $this->hasMany(ProductoSerie::class); }
    public function existencia(){ return $this->hasOne(ProductoExistencia::class); }
    public function movimientos(){ return $this->hasMany(ProductoMovimiento::class); }

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
