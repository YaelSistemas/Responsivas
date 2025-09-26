<?php

namespace App\Http\Controllers;

use App\Models\Responsiva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class PublicResponsivaController extends Controller
{
    // Muestra la página pública para firmar
    public function show(string $token)
    {
        $resp = Responsiva::where('sign_token', $token)->firstOrFail();

        // valida expiración si existe
        if ($resp->sign_token_expires_at && Carbon::parse($resp->sign_token_expires_at)->isPast()) {
            abort(410, 'El enlace de firma ha expirado.');
        }

        return view('public.responsivas.sign', [
            'responsiva' => $resp,
        ]);
    }

    // Guarda la firma enviada
    public function store(Request $req, string $token)
    {
        $resp = Responsiva::where('sign_token', $token)->firstOrFail();

        // valida expiración
        if ($resp->sign_token_expires_at && Carbon::parse($resp->sign_token_expires_at)->isPast()) {
            return back()->withErrors(['firma' => 'El enlace de firma ha expirado. Solicita uno nuevo.']);
        }

        $req->validate([
            'firma' => ['required'], // puede llegar como dataURL base64 o como archivo
            // opcional: nombre visible del firmante
            'nombre' => ['nullable','string','max:255'],
        ]);

        // Obtiene bytes PNG desde base64 o archivo subido
        $pngBytes = null;

        $firma = $req->input('firma');
        if (is_string($firma) && Str::startsWith($firma, 'data:image')) {
            // formato dataURL: "data:image/png;base64,AAAA..."
            [$meta, $data] = explode(',', $firma, 2);
            $pngBytes = base64_decode($data);
        } elseif ($req->hasFile('firma')) {
            $pngBytes = file_get_contents($req->file('firma')->getRealPath());
        }

        if (!$pngBytes) {
            return back()->withErrors(['firma' => 'No se pudo leer la firma.']);
        }

        // Guarda en storage público
        $dir  = 'firmas_colaboradores';
        $path = "{$dir}/responsiva-{$resp->id}.png";
        Storage::disk('public')->put($path, $pngBytes);

        // Actualiza campos en la responsiva
        $resp->firma_colaborador_path = $path;
        $resp->firmado_en   = now();
        $resp->firmado_por  = $req->input('nombre') ?: ($resp->colaborador->nombre ?? null);
        $resp->firmado_ip   = $req->ip();

        // invalida el token (opcional pero recomendado)
        $resp->sign_token = null;
        $resp->sign_token_expires_at = null;

        $resp->save();

        // Vista de “gracias” con botón a PDF
        return view('public.responsivas.signed', [
            'responsiva' => $resp,
            'pdf_url'    => route('responsivas.pdf', $resp),
            'firma_url'  => Storage::url($path),
        ]);
    }

    public function pdf(string $token)
{
    $responsiva = \App\Models\Responsiva::where('sign_token', $token)->firstOrFail();

    $html = view('responsivas.pdf', compact('responsiva'))->render();

    // Si usas Dompdf
    $pdf = \PDF::loadHTML($html)->setPaper('A4', 'portrait');
    return $pdf->stream("responsiva-{$responsiva->folio}.pdf"); // inline
}

}
