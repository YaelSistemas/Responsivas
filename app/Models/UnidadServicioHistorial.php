<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadServicioHistorial extends Model
{
    use HasFactory;

    protected $table = 'unidades_servicio_historial';

    protected $fillable = [
        'unidad_id',
        'user_id',
        'accion',
        'cambios',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function unidad()
    {
        return $this->belongsTo(UnidadServicio::class, 'unidad_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
