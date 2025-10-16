<?php

// app/Http/Controllers/OcAdjuntoController.php
namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\OcAdjunto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OcAdjuntoController extends Controller
{
    protected function canManage(): bool
    {
        $u = Auth::user();
        return $u->hasAnyRole(['Administrador','Compras Superior']) || $u->can('oc.edit');
    }

    public function modal(OrdenCompra $oc)
    {
        // Puede ver el modal cualquiera con acceso a OC; para subir/eliminar se verifica en acciones
        $oc->load(['adjuntos' => fn($q) => $q->latest()]);
        return view('oc.adjuntos.modal', compact('oc'));
    }

    public function store(Request $request, OrdenCompra $oc)
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'files'   => ['required','array','max:10'],
            'files.*' => ['file','max:10240','mimes:pdf,xml,jpg,jpeg,png,zip'],
            'nota'    => ['nullable','string','max:255'],
        ]);

        $disk = 'public';
        $created = 0;

        foreach ($data['files'] as $file) {
            $path = $file->store("oc/{$oc->id}", $disk);

            OcAdjunto::create([
                'orden_compra_id' => $oc->id,
                'disk'           => $disk,
                'path'           => $path,
                'original_name'  => $file->getClientOriginalName(),
                'mime'           => $file->getClientMimeType(),
                'size'           => $file->getSize(),
                'nota'           => $data['nota'] ?? null,
                'created_by'     => Auth::id(),
            ]);
            $created++;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'    => true,
                'msg'   => $created.' archivo(s) adjuntado(s).',
                'count' => $oc->adjuntos()->count(),
            ]);
        }

        return back()->with('updated', true);
    }

    public function download(OcAdjunto $adjunto)
    {
        return Storage::disk($adjunto->disk)->download($adjunto->path, $adjunto->original_name);
    }

    public function destroy(OcAdjunto $adjunto, Request $request)
    {
        abort_unless($this->canManage(), 403);
        $ocId = $adjunto->orden_compra_id;

        Storage::disk($adjunto->disk)->delete($adjunto->path);
        $adjunto->delete();

        if ($request->wantsJson() || $request->ajax()) {
            $count = OcAdjunto::where('orden_compra_id',$ocId)->count();
            return response()->json(['ok'=>true,'msg'=>'Adjunto eliminado.','count'=>$count]);
        }

        return back()->with('updated', true);
    }
}
