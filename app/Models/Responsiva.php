<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Responsiva extends Model
{
    protected $fillable = [
        'folio','colaborador_id','user_id','fecha_entrega','observaciones',
        'motivo_entrega','recibi_colaborador_id','autoriza_user_id',
    ];

    public function entrego()   { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
    public function autoriza()  { return $this->belongsTo(\App\Models\User::class, 'autoriza_user_id'); }
    public function recibi()    { return $this->belongsTo(\App\Models\Colaborador::class, 'recibi_colaborador_id'); }
    public function colaborador(){ return $this->belongsTo(Colaborador::class); }
    public function usuario(){ return $this->belongsTo(User::class,'user_id'); }
    public function detalles(){ return $this->hasMany(ResponsivaDetalle::class); }
}
