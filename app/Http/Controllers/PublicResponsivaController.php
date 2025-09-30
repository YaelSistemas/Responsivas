<?php

namespace App\Http\Controllers;

use App\Models\Responsiva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class PublicResponsivaController extends Controller
{
    // Muestra la página pública para firmar
    public function show(string $token)
    {
        $resp = Responsiva::where('sign_token', $token)->firstOrFail();

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

        if ($resp->sign_token_expires_at && Carbon::parse($resp->sign_token_expires_at)->isPast()) {
            return back()->withErrors(['firma' => 'El enlace de firma ha expirado. Solicita uno nuevo.']);
        }

        $req->validate([
            'firma'  => ['required'],
            'nombre' => ['nullable','string','max:255'],
        ]);

        // Obtiene bytes PNG desde base64 o archivo subido
        $pngBytes = null;
        $firma = $req->input('firma');
        if (is_string($firma) && Str::startsWith($firma, 'data:image')) {
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

        // Actualiza responsiva
        $resp->firma_colaborador_path = $path;
        $resp->firmado_en  = now();
        $resp->firmado_por = $req->input('nombre') ?: ($resp->colaborador->nombre ?? null);
        $resp->firmado_ip  = $req->ip();

        // Invalida token
        $resp->sign_token = null;
        $resp->sign_token_expires_at = null;
        $resp->save();

        // URL pública firmada por ID (sin login)
        $pdfPublicUrl = URL::signedRoute('public.responsivas.pdf', [
            'responsiva' => $resp->id,
        ]);

        return view('public.responsivas.signed', [
            'responsiva' => $resp,
            'pdf_url'    => $pdfPublicUrl,
            'firma_url'  => Storage::url($path),
        ]);
    }

    // PDF por token (antes de firmar)
    public function pdf(string $token)
    {
        $responsiva = \App\Models\Responsiva::where('sign_token', $token)->firstOrFail();

        // Relaciones base
        $with = [
            'colaborador',
            'detalles.producto', 'detalles.serie',
            'entrego', 'autoriza',
        ];

        // Cargar relaciones de Colaborador solo si existen
        foreach (['area','departamento','sede','unidadServicio','unidad_servicio'] as $rel) {
            if (method_exists(\App\Models\Colaborador::class, $rel)) {
                $with[] = "colaborador.$rel";
            }
        }
        $responsiva->load($with);

        $empresaPublicId = $responsiva->empresa_tenant_id ?? $responsiva->empresa_id ?? null;

        $html = view('responsivas.pdf', [
            'responsiva'      => $responsiva,
            'empresaPublicId' => $empresaPublicId,
        ])->render();

        $pdf = \PDF::loadHTML($html)->setPaper('A4', 'portrait');
        return $pdf->stream("responsiva-{$responsiva->folio}.pdf");
    }

    // PDF público por ID con URL firmada (después de firmar)
    public function pdfById(Request $request, Responsiva $responsiva)
    {
        $with = [
            'colaborador',
            'detalles.producto', 'detalles.serie',
            'entrego', 'autoriza',
        ];
        foreach (['area','departamento','sede','unidadServicio','unidad_servicio'] as $rel) {
            if (method_exists(\App\Models\Colaborador::class, $rel)) {
                $with[] = "colaborador.$rel";
            }
        }
        $responsiva->load($with);

        $empresaPublicId = $responsiva->empresa_tenant_id ?? $responsiva->empresa_id ?? null;

        $html = view('responsivas.pdf', [
            'responsiva'      => $responsiva,
            'empresaPublicId' => $empresaPublicId,
        ])->render();

        $pdf = \PDF::loadHTML($html)->setPaper('A4', 'portrait');
        return $pdf->stream("responsiva-{$responsiva->folio}.pdf");
    }
}
