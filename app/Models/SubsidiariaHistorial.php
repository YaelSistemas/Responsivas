<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubsidiariaHistorial extends Model
{
    use HasFactory;

    protected $table = 'subsidiarias_historial';

    protected $fillable = [
        'subsidiaria_id',
        'user_id',
        'accion',
        'cambios',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function subsidiaria()
    {
        return $this->belongsTo(Subsidiaria::class);
    }
}
