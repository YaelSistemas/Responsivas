<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductoExistencia extends Model
{
    use HasFactory;

    protected $table = 'producto_existencias';

    protected $fillable = ['empresa_tenant_id','producto_id','cantidad'];

    public function producto() {
        return $this->belongsTo(Producto::class);
    }

    public function scopeDeEmpresa($q, int $tenantId) {
        return $q->where('empresa_tenant_id', $tenantId);
    }

    protected static function booted() {
        static::creating(function (self $m) {
            $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id);
            $m->empresa_tenant_id ??= $tenant;
        });
    }

    public function movimientos() { 
        return $this->hasMany(ProductoMovimiento::class, 'producto_id', 'producto_id'); 
    }

}
