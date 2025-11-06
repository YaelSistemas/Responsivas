<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuestoHistorial extends Model
{
    use HasFactory;

    protected $table = 'puestos_historial';
    protected $fillable = ['puesto_id', 'user_id', 'accion', 'cambios'];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function puesto()
    {
        return $this->belongsTo(Puesto::class);
    }
}
