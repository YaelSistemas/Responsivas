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
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DevolucionController extends Controller implements HasMiddleware
{
    /**
     * ======== MIDDLEWARE DE PERMISOS ========
     * (idéntico al de OrdenCompraController)
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:devoluciones.view',   only: ['index', 'show', 'pdf']),
            new Middleware('permission:devoluciones.create', only: ['create', 'store']),
            new Middleware('permission:devoluciones.edit',   only: ['edit', 'update']),
            new Middleware('permission:devoluciones.delete', only: ['destroy']),
        ];
    }

    /* ===================== LISTADO ===================== */

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
                $query->where('folio', 'like', "%$q%")
                    ->orWhere('motivo', 'like', "%$q%")
                    ->orWhereHas('responsiva', function ($r) use ($q) {
                        $r->where('folio', 'like', "%$q%")
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
            ->orderByRaw("CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED) DESC")
            ->paginate($perPage);

        if ($request->ajax() && $request->has('partial')) {
            return view('devoluciones.partials.table', compact('devoluciones'))->render();
        }

        return view('devoluciones.index', compact('devoluciones', 'q', 'perPage'));
    }

    /* ===================== CREAR ===================== */

    public function create()
    {
        $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id ?? auth()->user()?->empresa_tenant_id);

        $seriesAsignadas = ProductoSerie::where('estado', 'Asignado')
            ->whereHas('producto', fn($q) => $q->where('empresa_tenant_id', $tenant))
            ->pluck('id');

        if ($seriesAsignadas->isEmpty()) {
            $responsivas = collect();
        } else {
            $ultimaFilaPorSerie = ResponsivaDetalle::query()
                ->whereIn('producto_serie_id', $seriesAsignadas)
                ->select('producto_serie_id', DB::raw('MAX(id) as detalle_id'))
                ->groupBy('producto_serie_id')
                ->pluck('detalle_id')
                ->toArray();

            $detallesActuales = ResponsivaDetalle::with(['producto', 'serie', 'responsiva.colaborador'])
                ->whereIn('id', $ultimaFilaPorSerie)
                ->get();

            $detallesPorResponsiva = $detallesActuales->groupBy('responsiva_id');

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

        // 🔹 Solo usuarios con rol Administrador
        $admins = User::role('Administrador')->orderBy('name')->get(['id', 'name']);

        // 🔹 Solo colaboradores ACTIVOS del tenant actual
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenant)
            ->where('activo', 1) //
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->get(['id', 'nombre', 'apellidos']);

        // 🔹 Si el usuario autenticado es admin, lo deja como valor por defecto
        $user = auth()->user();
        $adminDefault = ($user && $user->hasRole('Administrador')) ? $user->id : null;

        return view('devoluciones.create', compact('responsivas', 'admins', 'colaboradores', 'adminDefault'));
    }

    /* ===================== GUARDAR ===================== */

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

        // Declaramos la variable fuera del closure
        $devolucion = null;

        // Ejecutamos la transacción y devolvemos el modelo
        $devolucion = DB::transaction(function () use ($validated, $request) {
            $devolucion = Devolucion::create($validated);

            foreach ($request->productos as $productoId => $series) {
                $series = is_array($series) ? $series : [$series];
                foreach ($series as $serieId) {
                    if (!$serieId) continue;

                    $serie = ProductoSerie::find($serieId);
                    if (!$serie) continue;

                    $yaDevuelta = DB::table('devolucion_producto as dp')
                        ->join('devoluciones as d', 'd.id', '=', 'dp.devolucion_id')
                        ->where('dp.producto_serie_id', $serieId)
                        ->where('d.responsiva_id', $validated['responsiva_id'])
                        ->exists();

                    if ($yaDevuelta) continue;

                    $devolucion->productos()->attach($productoId, [
                        'producto_serie_id' => $serieId,
                    ]);

                    $serie->update(['estado' => 'disponible']);
                }
            }

            // 🔹 Retornar el modelo creado desde la transacción
            return $devolucion;
        });

        // 🔹 Redirigir directamente al show
        return redirect()
            ->route('devoluciones.show', $devolucion->id)
            ->with('success', 'Devolución registrada correctamente.');
    }

    /* ===================== EDITAR ===================== */

    public function edit($id)
    {
        $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id ?? auth()->user()?->empresa_tenant_id);

        $devolucion = Devolucion::with(['productos', 'responsiva.colaborador', 'psitioColaborador'])
            ->findOrFail($id);

        // 🔹 Solo usuarios con rol Administrador
        $admins = User::role('Administrador')->orderBy('name')->get(['id', 'name']);

        // 🔹 Solo colaboradores ACTIVOS del tenant actual
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenant)
            ->where('activo', 1)
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->get(['id', 'nombre', 'apellidos']);

        // 🔹 Responsivas del tenant actual (sin filtrar colaboradores)
        $responsivas = Responsiva::with('colaborador')
            ->where('empresa_tenant_id', $tenant)
            ->get();

        // 🔹 Obtener las series más recientes asociadas a la responsiva
        $ultimaFilaPorSerie = ResponsivaDetalle::query()
            ->where('responsiva_id', $devolucion->responsiva_id)
            ->select('producto_serie_id', DB::raw('MAX(id) as detalle_id'))
            ->groupBy('producto_serie_id')
            ->pluck('detalle_id')
            ->toArray();

        $detallesActuales = ResponsivaDetalle::with(['producto', 'serie'])
            ->whereIn('id', $ultimaFilaPorSerie)
            ->get();

        $seriesSeleccionadas = $devolucion->productos->pluck('pivot.producto_serie_id')->toArray();

        return view('devoluciones.edit', compact(
            'devolucion', 'admins', 'colaboradores', 'responsivas',
            'detallesActuales', 'seriesSeleccionadas'
        ));
    }

    /* ===================== ACTUALIZAR ===================== */

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
            $devolucion->update($validated);

            $seriesPrevias = $devolucion->productos()->pluck('devolucion_producto.producto_serie_id')->toArray();

            $seriesNuevas = collect($request->productos)
                ->flatMap(fn($series) => is_array($series) ? $series : [$series])
                ->filter()->unique()->values()->toArray();

            $agregadas = array_diff($seriesNuevas, $seriesPrevias);
            $quitadas  = array_diff($seriesPrevias, $seriesNuevas);

            // ➕ Series nuevas
            foreach ($request->productos as $productoId => $series) {
                $series = is_array($series) ? $series : [$series];
                foreach ($series as $serieId) {
                    if (in_array($serieId, $agregadas)) {
                        $serie = ProductoSerie::find($serieId);
                        if ($serie) {
                            $devolucion->productos()->attach($productoId, [
                                'producto_serie_id' => $serieId,
                            ]);
                            $serie->update(['estado' => 'disponible']);
                        }
                    }
                }
            }

            // ➖ Series quitadas
            foreach ($quitadas as $serieId) {
                $serie = ProductoSerie::find($serieId);
                if ($serie) {
                    $devolucion->productos()->wherePivot('producto_serie_id', $serieId)->detach();
                    $serie->update(['estado' => 'Asignado']);
                    if ($serie->producto) {
                        $serie->producto->update(['estado' => 'Asignado']);
                    }
                }
            }
        });

        // ✅ Redirigir directamente al show
        return redirect()
            ->route('devoluciones.show', $devolucion->id)
            ->with('success', 'Devolución actualizada correctamente.');
    }

    /* ===================== MOSTRAR ===================== */

    public function show($id)
    {
        $devolucion = Devolucion::with('responsiva.colaborador', 'productos', 'recibidoPor')->findOrFail($id);
        return view('devoluciones.show', compact('devolucion'));
    }

    /* ===================== ELIMINAR ===================== */

    public function destroy($id)
    {
        $devolucion = Devolucion::with(['productos', 'responsiva'])->findOrFail($id);

        DB::transaction(function () use ($devolucion) {
            foreach ($devolucion->productos as $producto) {
                $pivot = $producto->pivot;
                $serie = ProductoSerie::find($pivot->producto_serie_id);

                if (!$serie) continue;

                $ultimaDevolucionId = Devolucion::whereHas('productos', fn($q) => 
                    $q->where('producto_serie_id', $serie->id)
                )->max('id');

                $asignadaEnOtra = ResponsivaDetalle::where('producto_serie_id', $serie->id)
                    ->whereHas('responsiva', fn($q) => 
                        $q->whereIn('motivo_entrega', ['asignacion', 'prestamo_provisional'])
                    )
                    ->exists();

                if ($devolucion->id == $ultimaDevolucionId) {
                    $serie->update(['estado' => 'Asignado']);
                    if ($serie->producto) $serie->producto->update(['estado' => 'Asignado']);
                }
            }

            $devolucion->productos()->detach();
            $devolucion->delete();
        });

        return redirect()->route('devoluciones.index')
            ->with('success', 'Devolución eliminada correctamente.');
    }

    /* ===================== PDF ===================== */

    public function pdf($id)
    {
        $devolucion = Devolucion::with([
            'responsiva.colaborador.unidadServicio',
            'productos',
            'recibidoPor',
            'psitioColaborador'
        ])->findOrFail($id);

        $pdf = \PDF::loadView('devoluciones.pdf_sheet', compact('devolucion'));
        return $pdf->stream('Devolución-'.$devolucion->folio.'.pdf');
    }
}
