<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    
    protected $fillable = [
        'nombre',
        'rfc',
        'calle',
        'colonia',
        'codigo_postal',
        'ciudad',
        'estado',
        'empresa_tenant_id',
        'activo',              
    ];

    protected $casts = [
        'activo' => 'boolean', 
    ];

    public function scopeTenant($q, int $tenantId){
        return $q->where('empresa_tenant_id', $tenantId);
    }

    public function ordenes()
    {
        return $this->hasMany(OrdenCompra::class, 'proveedor_id');
    }

    // Texto de dirección listo para mostrar
    public function getDireccionLineaAttribute(): string
    {
        $partes = array_filter([
            $this->calle,
            $this->colonia,
            $this->codigo_postal ? 'CP '.$this->codigo_postal : null,
            $this->ciudad,
            $this->estado,
        ]);
        return implode(', ', $partes);
    }
}
