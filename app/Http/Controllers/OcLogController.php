<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OcLog;

class OcLogController extends Controller
{
    public function modal(OrdenCompra $oc)
    {
        // Trae los últimos 300 logs (con usuario) de esa OC
        $logs = OcLog::with('user')
            ->where('orden_compra_id', $oc->id)
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('oc.historial.modal', compact('oc', 'logs'));
    }
}
