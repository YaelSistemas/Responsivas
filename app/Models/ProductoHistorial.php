<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoHistorial extends Model
{
    use HasFactory;

    protected $table = 'productos_historial';

    protected $fillable = [
        'producto_id',
        'user_id',
        'accion',
        'datos_anteriores',
        'datos_nuevos',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
