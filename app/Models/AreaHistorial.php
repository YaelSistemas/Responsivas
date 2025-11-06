<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaHistorial extends Model
{
    protected $table = 'areas_historial';

    protected $fillable = [
        'area_id',
        'user_id',
        'accion',
        'cambios',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
