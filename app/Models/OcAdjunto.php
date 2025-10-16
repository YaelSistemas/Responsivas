<?php

// app/Models/OcAdjunto.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcAdjunto extends Model
{
    protected $table = 'oc_adjuntos';
    protected $fillable = [
        'orden_compra_id','disk','path','original_name','mime','size','nota','created_by'
    ];

    public function oc() {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function url(): string
    {
        return \Storage::disk($this->disk)->url($this->path);
    }
}
