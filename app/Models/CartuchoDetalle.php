<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartuchoDetalle extends Model
{
    protected $table = 'cartucho_detalles';

    protected $fillable = [
        'cartucho_id',
        'producto_id',
        'cantidad',
    ];

    public function cartucho(): BelongsTo
    {
        return $this->belongsTo(Cartucho::class, 'cartucho_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
