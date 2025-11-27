<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ResponsivaHistorial extends Model
{
    protected $table = 'responsivas_historial';

    protected $fillable = [
        'responsiva_id',
        'user_id',
        'accion',
        'cambios'
    ];

    protected $casts = [
        'cambios' => 'array'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responsiva()
    {
        return $this->belongsTo(Responsiva::class, 'responsiva_id');
    }
}
