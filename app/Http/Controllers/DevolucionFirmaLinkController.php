<?php

namespace App\Http\Controllers;

use App\Models\Devolucion;
use App\Models\DevolucionFirmaLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DevolucionFirmaLinkController extends Controller
{
    /**
     * Generar o renovar link de firma (ENTREGÓ o PSITIO).
     */
    public function generarLink(Request $request, Devolucion $devolucion)
    {
        // el botón manda 'entrego' o 'psitio'
        $campo = $request->input('campo', 'entrego');

        $link = DB::transaction(function () use ($devolucion, $campo) {
            $token = Str::random(32);

            // un registro por devolucion + campo
            $devolucion->firmaLinks()
                ->updateOrCreate(
                    [
                        'devolucion_id' => $devolucion->id,
                        'campo'         => $campo,
                    ],
                    [
                        'token'      => $token,
                        'expires_at' => now()->addDays(7),
                        'signed_at'  => null,
                    ]
                );

            return route('devoluciones.firmaExterna.show', $token);
        });

        return response()->json([
            'link' => $link,
        ]);
    }

    /**
     * Vista pública para firmar la devolución.
     */
    public function showForm(string $token)
    {
        $firmaLink = DevolucionFirmaLink::where('token', $token)->firstOrFail();

        if ($firmaLink->isExpired()) {
            abort(403, 'El enlace de firma ha expirado.');
        }

        if ($firmaLink->isSigned()) {
            abort(403, 'Este enlace ya fue utilizado para firmar.');
        }

        $devolucion = $firmaLink->devolucion;
        $campo      = $firmaLink->campo ?? 'entrego';

        return view('devoluciones.firma-externa', [
            'devolucion' => $devolucion,
            'firmaLink'  => $firmaLink,
            'campo'      => $campo, // por si quieres cambiar textos en la vista
        ]);
    }

    /**
     * Guarda la firma enviada desde el formulario público.
     */
    public function guardarFirma(Request $request, string $token)
    {
        $request->validate([
            'firma' => 'required|string',
        ]);

        $firmaLink = DevolucionFirmaLink::where('token', $token)->firstOrFail();

        if ($firmaLink->isExpired()) {
            return back()->withErrors('El enlace de firma ha expirado.');
        }

        if ($firmaLink->isSigned()) {
            return back()->withErrors('Este enlace ya fue utilizado para firmar.');
        }

        $devolucion = $firmaLink->devolucion;
        $campo      = $firmaLink->campo ?? 'entrego';  // 'entrego' o 'psitio'

        // ==== Guardar imagen ====
        $data = $request->input('firma');

        if (str_starts_with($data, 'data:image')) {
            [, $content] = explode(',', $data, 2);
        } else {
            $content = $data;
        }

        $pngData = base64_decode($content);

        $folder   = 'firmas_devoluciones';
        $filename = 'dev-'.$devolucion->id.'-'.$campo.'-'.time().'.png';

        Storage::disk('public')->put($folder.'/'.$filename, $pngData);

        // según el campo, guardamos en una u otra columna
        if ($campo === 'psitio') {
            $devolucion->firma_psitio_path = $folder.'/'.$filename;
        } else {
            $devolucion->firma_entrego_path = $folder.'/'.$filename;
        }

        $devolucion->save();

        $firmaLink->signed_at = Carbon::now();
        $firmaLink->save();

        return view('devoluciones.firma-externa-ok', [
            'devolucion' => $devolucion,
            'campo'      => $campo,
        ]);
    }
}
