<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Colaborador;

class ColaboradorHistorial extends Model
{
    use HasFactory;

    protected $table = 'colaboradores_historial';

    protected $fillable = [
        'colaborador_id',
        'user_id',
        'accion',
        'cambios',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_id');
    }
}
