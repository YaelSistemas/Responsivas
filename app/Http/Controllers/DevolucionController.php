<?php

namespace App\Http\Controllers;

use App\Models\Devolucion;
use App\Models\Responsiva;
use Illuminate\Http\Request;
use App\Models\ProductoSerie;
use Illuminate\Support\Facades\DB;
use App\Models\ResponsivaDetalle;
use App\Models\User;
use App\Models\Colaborador;

class DevolucionController extends Controller
{
    public function index(Request $request)
{
    $q = $request->input('q');
    $perPage = $request->input('per_page', 50);

    $devoluciones = Devolucion::with([
            'responsiva.colaborador.unidadServicio',
            'productos',
            'recibidoPor',
            'psitioColaborador'
        ])
        ->when($q, function ($query) use ($q) {
    $query->where('folio', 'like', "%$q%") // Folio de devolución
        ->orWhere('motivo', 'like', "%$q%") // Motivo directo
        ->orWhereHas('responsiva', function ($r) use ($q) {
            $r->where('folio', 'like', "%$q%") // Folio de responsiva
              ->orWhereHas('colaborador', function ($c) use ($q) {
                  $c->where('nombre', 'like', "%$q%")
                    ->orWhere('apellidos', 'like', "%$q%");
              });
        })
        ->orWhereHas('psitioColaborador', function ($p) use ($q) {
            $p->where('nombre', 'like', "%$q%")
              ->orWhere('apellidos', 'like', "%$q%");
        });
})

        // 🔹 Ordenar por número dentro del folio: ejemplo OES-0010 > OES-0009 > OES-0008
        ->orderByRaw("CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED) DESC")
        ->paginate($perPage);

    // 🔹 Soporte AJAX parcial (para búsquedas dinámicas)
    if ($request->ajax() && $request->has('partial')) {
        return view('devoluciones.partials.table', compact('devoluciones'))->render();
    }

    return view('devoluciones.index', compact('devoluciones', 'q', 'perPage'));
}


    public function create()
{
    $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id ?? auth()->user()?->empresa_tenant_id);

    // 1) Series actualmente asignadas (solo de la empresa activa)
    $seriesAsignadas = ProductoSerie::where('estado', 'Asignado')
        ->whereHas('producto', fn($q) => $q->where('empresa_tenant_id', $tenant))
        ->pluck('id');

    if ($seriesAsignadas->isEmpty()) {
        $responsivas = collect(); // nada que mostrar
    } else {
        // 2) Último detalle por serie
        $ultimaFilaPorSerie = ResponsivaDetalle::query()
            ->whereIn('producto_serie_id', $seriesAsignadas)
            ->select('producto_serie_id', DB::raw('MAX(id) as detalle_id'))
            ->groupBy('producto_serie_id')
            ->pluck('detalle_id')
            ->toArray();

        // 3) Detalles más recientes con relaciones
        $detallesActuales = ResponsivaDetalle::with([
                'producto',
                'serie',
                'responsiva.colaborador',
            ])
            ->whereIn('id', $ultimaFilaPorSerie)
            ->get();

        // 4) Agrupar por responsiva actual
        $detallesPorResponsiva = $detallesActuales->groupBy('responsiva_id');

        // 5) Cargar las responsivas activas con solo sus detalles actuales (de la empresa activa)
        $responsivas = Responsiva::with('colaborador')
            ->whereIn('id', $detallesPorResponsiva->keys())
            ->where('empresa_tenant_id', $tenant)
            ->get()
            ->map(function ($r) use ($detallesPorResponsiva) {
                $r->setRelation('detalles', $detallesPorResponsiva->get($r->id, collect()));
                return $r;
            })
            ->values();
    }

    // Usuarios administradores
    $admins = User::role('Administrador')
        ->orderBy('name')
        ->get(['id', 'name']);

    // Colaboradores por empresa activa
    $colaboradores = Colaborador::where('empresa_tenant_id', $tenant)
        ->orderBy('nombre')
        ->get(['id', 'nombre', 'apellidos']);

    // Usuario autenticado (si es admin, se selecciona por defecto)
    $user = auth()->user();
    $adminDefault = ($user && $user->hasRole('Administrador')) ? $user->id : null;

    return view('devoluciones.create', compact('responsivas', 'admins', 'colaboradores', 'adminDefault'));
}

public function edit($id)
{
    $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id ?? auth()->user()?->empresa_tenant_id);

    $devolucion = Devolucion::with(['productos', 'responsiva.colaborador', 'psitioColaborador'])->findOrFail($id);

    $admins = User::role('Administrador')->orderBy('name')->get(['id', 'name']);
    $colaboradores = Colaborador::where('empresa_tenant_id', $tenant)->orderBy('nombre')->get(['id', 'nombre', 'apellidos']);
    $responsivas = Responsiva::with('colaborador')->where('empresa_tenant_id', $tenant)->get();

    // ✅ Series actualmente asignadas a esa responsiva (una por serie)
    $ultimaFilaPorSerie = ResponsivaDetalle::query()
        ->where('responsiva_id', $devolucion->responsiva_id)
        ->select('producto_serie_id', DB::raw('MAX(id) as detalle_id'))
        ->groupBy('producto_serie_id')
        ->pluck('detalle_id')
        ->toArray();

    $detallesActuales = ResponsivaDetalle::with(['producto', 'serie'])
        ->whereIn('id', $ultimaFilaPorSerie)
        ->get();

    // ✅ Series ya devueltas en ESTA devolución (para pre-checar)
    $seriesSeleccionadas = $devolucion->productos->pluck('pivot.producto_serie_id')->toArray();

    return view('devoluciones.edit', compact(
        'devolucion', 'admins', 'colaboradores', 'responsivas',
        'detallesActuales', 'seriesSeleccionadas'
    ));
}


public function update(Request $request, $id)
{
    $validated = $request->validate([
        'responsiva_id'           => 'required|exists:responsivas,id',
        'fecha_devolucion'        => 'required|date',
        'motivo'                  => 'required|in:baja_colaborador,renovacion',
        'recibi_id'               => 'required|exists:users,id',
        'entrego_colaborador_id'  => 'required|exists:colaboradores,id',
        'psitio_colaborador_id'   => 'required|exists:colaboradores,id',
        'productos'               => 'required|array|min:1',
    ]);

    $devolucion = Devolucion::with('productos')->findOrFail($id);

    DB::transaction(function () use ($devolucion, $validated, $request) {
        // 1️⃣ Actualizamos los campos principales
        $devolucion->update($validated);

        // 2️⃣ Obtener los productos originalmente ligados
        $productosAnteriores = $devolucion->productos()->pluck('producto_serie_id')->toArray();
        $productosNuevos = collect($request->productos)->filter()->values()->toArray();

        // 3️⃣ Detectar diferencias
        $agregados = array_diff($productosNuevos, $productosAnteriores);
        $quitados  = array_diff($productosAnteriores, $productosNuevos);

        // 4️⃣ Procesar los agregados (marcar como DISPONIBLES)
        foreach ($request->productos as $productoId => $serieId) {
            if (in_array($serieId, $agregados)) {
                $serie = ProductoSerie::find($serieId);
                if ($serie) {
                    $devolucion->productos()->attach($productoId, [
                        'producto_serie_id' => $serieId,
                    ]);
                    $serie->update(['estado' => 'disponible']);
                }
            }
        }

        // 5️⃣ Procesar los quitados (volver a ASIGNADOS)
        foreach ($quitados as $serieId) {
            $serie = ProductoSerie::find($serieId);
            if ($serie) {
                // eliminar del pivote
                $devolucion->productos()->wherePivot('producto_serie_id', $serieId)->detach();

                // marcar como asignado
                $serie->update(['estado' => 'Asignado']);
                if ($serie->producto) {
                    $serie->producto->update(['estado' => 'Asignado']);
                }
            }
        }
    });

    return redirect()
        ->route('devoluciones.index')
        ->with('success', 'Devolución actualizada correctamente. Los productos fueron sincronizados según los cambios.');
}





    public function store(Request $request)
{
    $validated = $request->validate([
        'responsiva_id'           => 'required|exists:responsivas,id',
        'fecha_devolucion'        => 'required|date',
        'motivo'                  => 'required|in:baja_colaborador,renovacion',
        'recibi_id'               => 'required|exists:users,id',
        'entrego_colaborador_id'  => 'required|exists:colaboradores,id',
        'psitio_colaborador_id'   => 'required|exists:colaboradores,id',
        'productos'               => 'required|array|min:1',
    ]);

    DB::transaction(function () use ($validated, $request) {
        $devolucion = \App\Models\Devolucion::create($validated);

        foreach ($request->productos as $productoId => $serieId) {
            if (!$serieId) continue;

            $serie = \App\Models\ProductoSerie::find($serieId);
            if (!$serie) continue;

            // ⛔ Bloquea solo si YA se devolvió esa serie EN ESTA MISMA RESPONSIVA
            $yaDevueltaEnEstaResponsiva = DB::table('devolucion_producto as dp')
                ->join('devoluciones as d', 'd.id', '=', 'dp.devolucion_id')
                ->where('dp.producto_serie_id', $serieId)
                ->where('d.responsiva_id', $validated['responsiva_id'])
                ->exists();

            if ($yaDevueltaEnEstaResponsiva) {
                continue; // ya estaba registrada la devolución para esta responsiva
            }

            // Registra la devolución de esta serie para esta responsiva
            $devolucion->productos()->attach($productoId, [
                'producto_serie_id' => $serieId,
            ]);

            // 🔁 Marca la serie como DISPONIBLE (o 'devuelto' si usas ese estado)
            $serie->update(['estado' => 'disponible']);
        }
    });

    return redirect()
        ->route('devoluciones.index')
        ->with('success', 'Devolución registrada correctamente.');
}


    public function show($id)
    {
        $devolucion = Devolucion::with('responsiva.colaborador', 'productos', 'recibidoPor')->findOrFail($id);
        return view('devoluciones.show', compact('devolucion'));
    }

    public function destroy($id)
{
    $devolucion = Devolucion::with(['productos', 'responsiva'])->findOrFail($id);

    DB::transaction(function () use ($devolucion) {
        foreach ($devolucion->productos as $producto) {
            $pivot = $producto->pivot;
            $serie = \App\Models\ProductoSerie::find($pivot->producto_serie_id);

            if (!$serie) {
                continue;
            }

            // ⚙️ 1️⃣ Obtener el ID de la última devolución registrada para este producto
            $ultimaDevolucionId = \App\Models\Devolucion::whereHas('productos', function ($q) use ($serie) {
                    $q->where('producto_serie_id', $serie->id);
                })
                ->max('id'); // ID de la devolución más reciente del producto

            // ⚙️ 2️⃣ Verificar si el producto está asignado en alguna otra responsiva activa
            $asignadaEnOtra = \App\Models\ResponsivaDetalle::where('producto_serie_id', $serie->id)
                ->whereHas('responsiva', function ($q) use ($devolucion) {
                    $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id ?? auth()->user()?->empresa_tenant_id);
                    $q->where('empresa_tenant_id', $tenant)
                      ->whereIn('motivo_entrega', ['asignacion', 'prestamo_provisional']);
                })
                ->exists();

            // ⚙️ 3️⃣ Lógica principal
            if ($devolucion->id == $ultimaDevolucionId) {
                // 🟢 Es la última devolución registrada para este producto
                //    → volver a "Asignado", ya que la devolución más reciente fue eliminada
                $serie->update(['estado' => 'Asignado']);
                if ($serie->producto) {
                    $serie->producto->update(['estado' => 'Asignado']);
                }
            } else {
                // 🔹 No es la última devolución → mantener Disponible
                //    (ya existe otra devolución posterior del mismo producto)
                continue;
            }
        }

        // 🧹 4️⃣ Eliminar la devolución y sus relaciones pivote
        $devolucion->productos()->detach();
        $devolucion->delete();
    });

    return redirect()
        ->route('devoluciones.index')
        ->with('success', 'Devolución eliminada correctamente. Los productos fueron restaurados a su estado correspondiente.');
}

public function pdf($id)
{
    $devolucion = \App\Models\Devolucion::with([
        'responsiva.colaborador.unidadServicio',
        'productos',
        'recibidoPor',
        'psitioColaborador'
    ])->findOrFail($id);

    $pdf = \PDF::loadView('devoluciones.pdf_sheet', compact('devolucion'));

    // 🔹 Abrir en el navegador (igual que el de OC)
    return $pdf->stream('Devolución-'.$devolucion->folio.'.pdf');
}



}
