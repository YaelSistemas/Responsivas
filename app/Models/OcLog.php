<?php

// app/Models/OcLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcLog extends Model
{
    protected $table = 'oc_logs';
    protected $fillable = ['orden_compra_id','user_id','type','data'];
    protected $casts = ['data' => 'array'];

    public function oc() { return $this->belongsTo(OrdenCompra::class, 'orden_compra_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
