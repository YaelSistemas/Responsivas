<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\ProductoSerie;
use App\Models\Responsiva;
use App\Models\Devolucion;

class ProductoSerieHistorial extends Model
{
    use HasFactory;

    protected $table = 'producto_series_historial';

    protected $fillable = [
        'empresa_tenant_id',
        'producto_serie_id',
        'producto_id',
        'user_id',
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'responsiva_id',
        'devolucion_id',
        'cambios',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function serie()
    {
        return $this->belongsTo(ProductoSerie::class, 'producto_serie_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responsiva()
    {
        return $this->belongsTo(Responsiva::class, 'responsiva_id');
    }

    public function devolucion()
    {
        return $this->belongsTo(Devolucion::class, 'devolucion_id');
    }
}
