<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResponsivaDetalle extends Model
{
    protected $table = 'responsiva_detalles';
    
    protected $fillable = ['responsiva_id','producto_id','producto_serie_id'];

    public function responsiva(){ return $this->belongsTo(Responsiva::class); }
    public function producto(){ return $this->belongsTo(Producto::class); }
    public function serie(){ return $this->belongsTo(ProductoSerie::class,'producto_serie_id'); }
}
