<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductoMovimiento extends Model
{
    use HasFactory;

    protected $table = 'producto_movimientos';

    protected $fillable = [
        'empresa_tenant_id','producto_id','user_id',
        'tipo','cantidad','motivo','referencia',
    ];

    public function producto(){ return $this->belongsTo(Producto::class); }
    public function usuario(){ return $this->belongsTo(User::class, 'user_id'); }

    public function scopeDeEmpresa($q, int $tenant){ return $q->where('empresa_tenant_id', $tenant); }

    protected static function booted(){
        static::creating(function(self $m){
            $m->empresa_tenant_id ??= (int) session('empresa_activa', auth()->user()?->empresa_id);
            $m->user_id          ??= auth()->id();
        });
    }
}
