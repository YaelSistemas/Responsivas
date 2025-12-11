<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\DevolucionFirmaLink;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'empresa_tenant_id',
        'folio',
        'responsiva_id',
        'fecha_devolucion',
        'motivo',
        'recibi_id',
        'entrego_colaborador_id',
        'psitio_colaborador_id',
        'firma_entrego_path',
        'firma_psitio_path',
    ];

    protected $casts = [
        'fecha_devolucion' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('empresa', function (Builder $q) {
            $empresaId = (int) session('empresa_activa', auth()->user()?->empresa_id);
            if ($empresaId) {
                $q->where('empresa_tenant_id', $empresaId);
            }
        });

        static::creating(function ($model) {
            $empresaId = (int) session('empresa_activa', auth()->user()?->empresa_id);
            $model->empresa_tenant_id = $empresaId;
            $model->folio = self::makeNextFolio($empresaId);
        });
    }

    public static function makeNextFolio(int $empresaId): string
    {
        $prefix = 'OES-';
        $last = static::withoutGlobalScopes()
            ->where('empresa_tenant_id', $empresaId)
            ->where('folio', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('folio');

        $seq = 1;
        if ($last && preg_match('/^OES-(\d+)$/', $last, $m)) {
            $seq = (int)$m[1] + 1;
        }

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    // Relaciones
    public function responsiva() 
    { 
        return $this->belongsTo(Responsiva::class); 
    }

    public function recibidoPor()
    {
        return $this->belongsTo(User::class, 'recibi_id');
    }

    public function entregoColaborador() 
    { 
        return $this->belongsTo(Colaborador::class, 'entrego_colaborador_id'); 
    }

    public function psitioColaborador() 
    { 
        return $this->belongsTo(Colaborador::class, 'psitio_colaborador_id'); 
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'devolucion_producto')
                    ->withPivot('producto_serie_id')
                    ->withTimestamps();
    }

    public function firmaLinks()
    {
        // un link para ENTREGÓ, otro para PSITIO
        return $this->hasMany(DevolucionFirmaLink::class);
    }
}
