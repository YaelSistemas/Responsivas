<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OcAdjunto;
use App\Models\OcLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OcAdjuntoController extends Controller
{
    /**
     * ¿El usuario puede administrar adjuntos de OC?
     */
    protected function canManage(): bool
    {
        $u = Auth::user();
        return $u->hasAnyRole(['Administrador', 'Compras Superior']) || $u->can('oc.edit');
    }

    /**
     * Modal HTML con los adjuntos de una OC.
     */
    public function modal(OrdenCompra $oc)
    {
        $oc->load(['adjuntos' => fn($q) => $q->latest()]);
        return view('oc.adjuntos.modal', compact('oc'));
    }

    /**
     * Subida (múltiple) de adjuntos.
     */
    public function store(Request $request, OrdenCompra $oc)
    {
        // Si se quiere que solo los que tenga permisos edit de OC puedan subir adjuntos descomentar la linea de abajo
        // abort_unless($this->canManage(), 403);

        // Permite 'file' (singular) además de 'files[]'
        if ($request->hasFile('file') && !$request->hasFile('files')) {
            $request->merge(['files' => [$request->file('file')]]);
        }

        $data = $request->validate([
            'files'   => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200', 'mimes:pdf,xml,jpg,jpeg,png,zip'],
            'nota'    => ['nullable', 'string', 'max:255'],
        ]);

        $disk    = 'public';
        $created = 0;

        foreach ($data['files'] as $file) {
            // 1) Guardar
            $path = $file->store("oc/{$oc->id}", $disk);

            // 2) Registrar
            $adj = OcAdjunto::create([
                'orden_compra_id' => $oc->id,
                'disk'            => $disk,
                'path'            => $path,
                'original_name'   => $file->getClientOriginalName(),
                'mime'            => $file->getClientMimeType(),
                'size'            => $file->getSize(),
                'nota'            => $data['nota'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            // 3) Log (incluye path)
            OcLog::create([
                'orden_compra_id' => $oc->id,
                'user_id'         => Auth::id(),
                'type'            => 'attachment_added',
                'data'            => [
                    'name' => $adj->original_name,
                    'size' => $adj->size,
                    'mime' => $adj->mime,
                    'path' => $adj->path,
                ],
            ]);

            $created++;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'    => true,
                'msg'   => $created === 1 ? '1 archivo adjuntado.' : "{$created} archivos adjuntados.",
                'count' => $oc->adjuntos()->count(),
            ], 200);
        }

        return back()->with('updated', true);
    }

    /**
     * Descargar adjunto.
     */
    public function download(OcAdjunto $adjunto)
    {
        $disk = $adjunto->disk ?: 'public';
        return Storage::disk($disk)->download($adjunto->path, $adjunto->original_name);
    }

    /**
     * Eliminar adjunto (físico + BD) con log.
     */
    public function destroy(Request $request, OcAdjunto $adjunto)
    {
        // Si se quiere que solo los que tenga permisos delete de OC puedan eliminar adjuntos descomentar la linea de abajo
        // abort_unless($this->canManage(), 403);

        // Toma el ID directo (no dependas de la relación)
        $ocId = $adjunto->orden_compra_id;

        // Cachea metadata ANTES de borrar
        $meta = [
            'name' => $adjunto->original_name,
            'size' => $adjunto->size,
            'mime' => $adjunto->mime,
            'path' => $adjunto->path,
        ];
        $disk = $adjunto->disk ?: 'public';

        try {
            // 1) Borrar archivo (no fallar si no existe)
            if (!empty($meta['path'])) {
                try { Storage::disk($disk)->delete($meta['path']); }
                catch (\Throwable $fsErr) {
                    \Log::warning('No se pudo borrar el archivo del adjunto', [
                        'adjunto_id' => $adjunto->id,
                        'path'       => $meta['path'],
                        'disk'       => $disk,
                        'err'        => $fsErr->getMessage(),
                    ]);
                }
            }

            // 2) Borrar registro en BD
            $adjunto->delete();

            // 3) Registrar SIEMPRE el evento (usando el ID crudo)
            OcLog::create([
                'orden_compra_id' => $ocId,
                'user_id'         => Auth::id(),
                'type'            => 'attachment_removed', // tu vista ya contempla removed/deleted
                'data'            => $meta,
            ]);

            // 4) Recuento actualizado (sin relación)
            $count = OcAdjunto::where('orden_compra_id', $ocId)->count();

            // 5) Responder AJAX
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok'    => true,
                    'msg'   => 'Adjunto eliminado.',
                    'count' => $count,
                ], 200);
            }

            return back()->with('updated', true);

        } catch (\Throwable $e) {
            \Log::error('Error al eliminar adjunto', [
                'adjunto_id' => $adjunto->id ?? null,
                'err'        => $e->getMessage(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo eliminar',
                    'error'   => $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'No se pudo eliminar');
        }
    }

}
