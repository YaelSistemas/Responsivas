<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductoSerieFoto extends Model
{
    protected $fillable = ['producto_serie_id','path','caption'];

    public function serie() {
        return $this->belongsTo(ProductoSerie::class, 'producto_serie_id');
    }

    protected $appends = ['url'];

    public function getUrlAttribute() {
        return url('storage/'.$this->path);
    }
}