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
use App\Models\ProductoSerieHistorial;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            new Middleware('permission:devoluciones.edit',   only: ['edit', 'update','firmarEnSitio']),
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

    public function create(Request $request)
    {
        $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id ?? auth()->user()?->empresa_tenant_id);

        // ✅ Si vienes desde /celulares/devoluciones/create
        $isCel = $request->routeIs('celulares.devoluciones.create');

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
                // ✅ si es modo celular, solo CEL-
                ->when($isCel, fn($q) => $q->where('folio', 'like', 'CEL-%'))
                // ✅ opcional: si NO es celular y quieres restringir a OES-
                // ->when(!$isCel, fn($q) => $q->where('folio', 'like', 'OES-%'))
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

        return view('devoluciones.create', compact('responsivas', 'admins', 'colaboradores', 'adminDefault', 'isCel'));
    }

    /* ===================== GUARDAR ===================== */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'responsiva_id'           => 'required|exists:responsivas,id',
            'fecha_devolucion'        => 'required|date',
            'motivo'                  => 'required|in:baja_colaborador,renovacion,resguardo',
            'recibi_id'               => 'required|exists:users,id',
            'entrego_colaborador_id'  => 'required|exists:colaboradores,id',
            'psitio_colaborador_id'   => 'required|exists:colaboradores,id',
            'productos'               => 'required|array|min:1',
        ]);

        $tenant = (int) session('empresa_activa', auth()->user()?->empresa_id ?? auth()->user()?->empresa_tenant_id);

        // ✅ Traer la responsiva para saber si es CEL-
        $responsiva = Responsiva::where('empresa_tenant_id', $tenant)
            ->findOrFail($validated['responsiva_id']);

        $isCel = str_starts_with((string) $responsiva->folio, 'CEL-');

        // ✅ Si es celular, el motivo SIEMPRE debe ser resguardo (aunque manipulen el form)
        if ($isCel) {
            $validated['motivo'] = 'resguardo';
        }

        // ✅ Prefijos (celular debe ser DEVCEL-00000)
        $prefix = $isCel ? 'DEVCEL-' : 'DEV-';   // si tu normal usa otro prefijo, cámbialo aquí

        $devolucion = null;

        // ================================
        //      TRANSACCIÓN PRINCIPAL
        // ================================
        $devolucion = DB::transaction(function () use ($validated, $request, $tenant, $prefix, $isCel) {

            // ✅ Generar folio por prefijo y tenant (con lock para evitar empalmes)
            $last = Devolucion::where('empresa_tenant_id', $tenant)
                ->where('folio', 'like', $prefix . '%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('folio');

            $next = 1;
            if ($last) {
                // extrae el número final del folio: DEVCEL-00012 => 12
                $num = (int) preg_replace('/\D+/', '', $last);
                $next = $num + 1;
            }

            // ✅ Asegurar campos obligatorios en BD
            $validated['empresa_tenant_id'] = $tenant;
            $validated['folio'] = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            unset($validated['productos']); // ✅ productos NO es columna de devoluciones

            // ✅ Ahora sí crear
            $devolucion = new Devolucion();
            $devolucion->forceFill($validated);
            $devolucion->save();

            foreach ($request->productos as $productoId => $series) {

                $series = is_array($series) ? $series : [$series];

                foreach ($series as $serieId) {
                    if (!$serieId) continue;

                    $serie = ProductoSerie::find($serieId);
                    if (!$serie) continue;

                    // Verificar si esa serie ya fue devuelta en esa responsiva
                    $yaDevuelta = DB::table('devolucion_producto as dp')
                        ->join('devoluciones as d', 'd.id', '=', 'dp.devolucion_id')
                        ->where('dp.producto_serie_id', $serieId)
                        ->where('d.responsiva_id', $validated['responsiva_id'])
                        ->exists();

                    if ($yaDevuelta) continue;

                    // Guardar relación
                    $devolucion->productos()->attach($productoId, [
                        'producto_serie_id' => $serieId,
                    ]);

                    // Cambiar estado de la serie
                    $serie->update(['estado' => 'disponible']);

                    // ================================
                    //  REGISTRAR HISTORIAL DE DEVOLUCIÓN
                    // ================================

                    // Obtener la última asignación real de esta serie
                    $ultAsigna = ResponsivaDetalle::with('responsiva.colaborador')
                        ->where('producto_serie_id', $serie->id)
                        ->orderBy('id', 'DESC')
                        ->first();

                    $colAnterior = $ultAsigna?->responsiva?->colaborador;

                    $nombreAnterior = $colAnterior
                        ? ($colAnterior->nombre . ' ' . $colAnterior->apellidos)
                        : null;

                    $usuarioRecibio = \App\Models\User::find($validated['recibi_id']);

                    // Obtener motivo real según última responsiva
                    $estadoAnteriorReal = strtolower(
                        $ultAsigna?->responsiva?->motivo_entrega ?? 'asignacion'
                    );

                    // Convertir a nombre amigable
                    $estadoAnteriorReal = match($estadoAnteriorReal) {
                        'prestamo_provisional' => 'Préstamo provisional',
                        'asignacion'           => 'Asignado',
                        default                => ucfirst($estadoAnteriorReal),
                    };

                    $serie->registrarHistorial([
                        'accion'          => 'devolucion',
                        'responsiva_id'   => $validated['responsiva_id'],
                        'devolucion_id'   => $devolucion->id,
                        'estado_anterior' => $estadoAnteriorReal,
                        'estado_nuevo'    => 'disponible',
                        'motivo_devolucion' => $validated['motivo'],

                        'cambios' => [
                            'removido_de' => [
                                'antes'   => $nombreAnterior,
                                'despues' => null,
                            ],
                            'actualizado_por' => [
                                'antes'   => null,
                                'despues' => $usuarioRecibio?->name,
                            ],
                        ],
                    ]);
                    // ================================
                }
            }

            return $devolucion;
        });

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

            // Actualizamos cabecera
            $devolucion->update($validated);

            // Series actuales en la BD
            $seriesPrevias = $devolucion->productos()
                ->pluck('devolucion_producto.producto_serie_id')
                ->toArray();

            // Series nuevas enviadas por el form
            $seriesNuevas = collect($request->productos)
                ->flatMap(fn($series) => is_array($series) ? $series : [$series])
                ->filter()->unique()->values()->toArray();

            $agregadas = array_diff($seriesNuevas, $seriesPrevias);
            $quitadas  = array_diff($seriesPrevias, $seriesNuevas);

            $usuarioRecibio = \App\Models\User::find($validated['recibi_id']);

            // ===============================
            //     ➕ SERIES AGREGADAS
            // ===============================
            foreach ($request->productos as $productoId => $series) {

                $series = is_array($series) ? $series : [$series];

                foreach ($series as $serieId) {
                    if (in_array($serieId, $agregadas)) {

                        $serie = ProductoSerie::find($serieId);
                        if (!$serie) continue;

                        // Adjuntar
                        $devolucion->productos()->attach($productoId, [
                            'producto_serie_id' => $serieId,
                        ]);

                        // Cambiar estado
                        $serie->update(['estado' => 'disponible']);

                        // Obtener la última responsiva donde estaba asignada la serie
                        $ultAsigna = ResponsivaDetalle::with('responsiva.colaborador')
                            ->where('producto_serie_id', $serie->id)
                            ->orderBy('id', 'DESC')
                            ->first();

                        $colAnterior = $ultAsigna?->responsiva?->colaborador;

                        $nombreAnterior = $colAnterior
                            ? ($colAnterior->nombre . ' ' . $colAnterior->apellidos)
                            : null;

                        $estadoAnteriorReal = strtolower(
                            $ultAsigna?->responsiva?->motivo_entrega ?? 'asignacion'
                        );

                        $estadoAnteriorReal = match($estadoAnteriorReal) {
                            'prestamo_provisional' => 'Préstamo provisional',
                            'asignacion'           => 'Asignado',
                            default                => ucfirst($estadoAnteriorReal),
                        };

                        // Registrar historial
                        $serie->registrarHistorial([
                            'accion'          => 'devolucion',
                            'responsiva_id'   => $validated['responsiva_id'],
                            'estado_anterior' => $estadoAnteriorReal,
                            'estado_nuevo'    => 'disponible',
                            'motivo_devolucion' => $validated['motivo'],

                            'cambios' => [
                                'removido_de' => [
                                    'antes'   => $nombreAnterior,
                                    'despues' => null,
                                ],
                                'actualizado_por' => [
                                    'antes'   => null,
                                    'despues' => $usuarioRecibio?->name,
                                ],
                            ],
                        ]);
                    }
                }
            }

            // ===============================
            //     ➖ SERIES REMOVIDAS
            // ===============================
            foreach ($quitadas as $serieId) {

                $serie = ProductoSerie::find($serieId);
                if (!$serie) continue;

                // Detach
                $devolucion->productos()
                    ->wherePivot('producto_serie_id', $serieId)
                    ->detach();

                // Regresar estado
                $serie->update(['estado' => 'Asignado']);
                if ($serie->producto) {
                    $serie->producto->update(['estado' => 'Asignado']);
                }

                // *** Registrar historial de reversión ***
                $serie->registrarHistorial([
                    'accion'          => 'reversion_devolucion',
                    'responsiva_id'   => $validated['responsiva_id'],
                    'estado_anterior' => 'disponible',
                    'estado_nuevo'    => 'Asignado',
                    'cambios' => [
                        'asignado_a' => [
                            'antes'   => null,
                            'despues' => 'Asignado nuevamente (edición de devolución)',
                        ],
                        'actualizado_por' => [
                            'antes'   => null,
                            'despues' => auth()->user()->name,
                        ],
                    ],
                ]);
            }
        });

        // Redirigir al show
        return redirect()
            ->route('devoluciones.show', $devolucion->id)
            ->with('success', 'Devolución actualizada correctamente.');
    }

    /* ===================== MOSTRAR ===================== */

    public function show($id)
    {
        $devolucion = Devolucion::with(
            'responsiva.colaborador.unidadServicio',
            'productos',
            'recibidoPor',
            'entregoColaborador',
            'psitioColaborador'
        )->findOrFail($id);

        return view('devoluciones.show', compact('devolucion'));
    }

    /* ===================== ELIMINAR ===================== */
    public function destroy($id)
    {
        $devolucion = Devolucion::with(['productos', 'responsiva.colaborador.subsidiaria'])
            ->findOrFail($id);

        // 📌 Guardar datos antes del borrado
        $folioDevolucion = $devolucion->folio ?? 'SIN FOLIO';
        $motivoOriginal  = $devolucion->motivo ?? '—';
        $fechaOriginal   = $devolucion->fecha_devolucion ?? null;
        $subsidiariaOrg  = $devolucion->responsiva?->colaborador?->subsidiaria?->descripcion ?? 'SIN SUBSIDIARIA';

        DB::transaction(function () use ($devolucion, $folioDevolucion, $motivoOriginal, $fechaOriginal, $subsidiariaOrg) {

            foreach ($devolucion->productos as $producto) {

                $pivot = $producto->pivot;
                $serie = ProductoSerie::find($pivot->producto_serie_id);
                if (!$serie) continue;

                /* ============================================================
                ⭐ A. GUARDAR EL FOLIO EN EL HISTORIAL ORIGINAL DE LA DEVOLUCIÓN
                ============================================================ */
                $logOriginal = ProductoSerieHistorial::where('producto_serie_id', $serie->id)
                    ->where('accion', 'devolucion')
                    ->where('devolucion_id', $devolucion->id)
                    ->first();

                if ($logOriginal) {
                    $cambios = $logOriginal->cambios;

                    // Guardamos el folio de la devolución
                    $cambios['devolucion_folio'] = [
                        'antes' => $folioDevolucion,
                        'despues' => null
                    ];

                    // Guardamos el folio de la responsiva asociada
                    $cambios['responsiva_folio'] = [
                        'antes' => $devolucion->responsiva?->folio ?? 'SIN FOLIO',
                        'despues' => null
                    ];

                    $logOriginal->update(['cambios' => $cambios]);
                }


                /* ============================================================
                1. RECUPERAR EL ESTADO ANTERIOR REAL
                ============================================================ */
                $ultAsigna = ResponsivaDetalle::with('responsiva')
                    ->where('producto_serie_id', $serie->id)
                    ->orderBy('id', 'DESC')
                    ->first();

                $estadoAnteriorReal = strtolower($ultAsigna?->responsiva?->motivo_entrega ?? 'asignacion');

                $estadoAnteriorReal = match ($estadoAnteriorReal) {
                    'prestamo_provisional' => 'Préstamo provisional',
                    'asignacion'           => 'Asignado',
                    'renovacion'           => 'Renovación',
                    'baja_colaborador'     => 'Baja colaborador',
                    default                => ucfirst($estadoAnteriorReal),
                };

                /* ============================================================
                2. Cambiar estado de la serie si es la última devolución
                ============================================================ */
                $ultimaDevolucionId = Devolucion::whereHas('productos', fn($q) =>
                    $q->where('producto_serie_id', $serie->id)
                )->max('id');

                if ($devolucion->id == $ultimaDevolucionId) {
                    $serie->update(['estado' => 'Asignado']);

                    if ($serie->producto)
                        $serie->producto->update(['estado' => 'Asignado']);
                }

                /* ============================================================
                3. Nombre del colaborador original
                ============================================================ */
                $colaborador = $devolucion->responsiva?->colaborador;
                $colabNombre = $colaborador
                    ? $colaborador->nombre . ' ' . $colaborador->apellidos
                    : 'SIN COLABORADOR';

                /* ============================================================
                4. REGISTRAR HISTORIAL DE ELIMINACIÓN
                ============================================================ */
                $serie->registrarHistorial([
                    'accion'          => 'liberado_eliminacion',
                    'responsiva_id'   => $devolucion->responsiva_id,
                    'devolucion_id'   => null,
                    'estado_anterior' => $estadoAnteriorReal,
                    'estado_nuevo'    => 'Disponible',

                    'cambios' => [

                        'asignado_a' => [
                            'antes'   => $colabNombre,
                            'despues' => null
                        ],

                        'eliminado_por' => [
                            'antes'   => null,
                            'despues' => auth()->user()->name
                        ],

                        'fecha_eliminacion' => [
                            'antes'   => null,
                            'despues' => now()->format('d-m-Y H:i')
                        ],

                        'motivo_devolucion' => [
                            'antes'   => $motivoOriginal,
                            'despues' => null
                        ],

                        'fecha_devolucion' => [
                            'antes'   => $fechaOriginal
                                ? \Carbon\Carbon::parse($fechaOriginal)->format('d-m-Y')
                                : 'SIN FECHA',
                            'despues' => null
                        ],

                        'subsidiaria' => [
                            'antes'   => $subsidiariaOrg,
                            'despues' => null
                        ],

                        'responsiva_folio' => [
                            'antes'   => $devolucion->responsiva?->folio ?? null,
                            'despues' => null
                        ],

                        'devolucion_folio' => [
                            'antes'   => $folioDevolucion,
                            'despues' => null
                        ],
                    ]
                ]);
            }

            /* ============================================================
            5. LIMPIAR RELACIONES Y ELIMINAR DEVOLUCIÓN
            ============================================================ */
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

    public function firmarEnSitio(Request $request, Devolucion $devolucion)
{
    // Recuerda que este método está protegido por permission:devoluciones.edit
    $request->validate([
        'campo' => 'required|in:entrego,psitio',
        'firma' => 'required|string', // dataURL base64
    ]);

    $campo   = $request->input('campo');      // 'entrego' o 'psitio'
    $dataUrl = $request->input('firma');

    if (!Str::startsWith($dataUrl, 'data:image')) {
        return back()->with('error', 'Formato de firma inválido.');
    }

    [$meta, $content] = explode(',', $dataUrl, 2);
    $binary = base64_decode($content);

    if (!$binary) {
        return back()->with('error', 'No se pudo procesar la firma.');
    }

    $ext  = 'png';
    $dir  = 'firmas_devoluciones';
    $name = "devolucion-{$devolucion->id}_{$campo}.{$ext}";
    $path = "{$dir}/{$name}";

    Storage::disk('public')->put($path, $binary);

    if ($campo === 'entrego') {
        $devolucion->firma_entrego_path = $path;
    } else {
        $devolucion->firma_psitio_path  = $path;
    }

    $devolucion->save();

    return redirect()
        ->route('devoluciones.show', $devolucion->id)
        ->with('success', 'Firma guardada correctamente.');
}

public function borrarFirmaEnSitio(Request $request, Devolucion $devolucion)
{
    // Si tienes política, puedes usar:
    // $this->authorize('update', $devolucion);
    // O tu gate de permiso:
    // $this->authorize('devoluciones.edit');

    $data = $request->validate([
        'campo' => 'required|in:entrego,psitio',
    ]);

    $campo = $data['campo'];

    if ($campo === 'entrego' && $devolucion->firma_entrego_path) {
        // Borrar archivo físico si existe
        if (Storage::exists($devolucion->firma_entrego_path)) {
            Storage::delete($devolucion->firma_entrego_path);
        }
        // Limpiar columna en BD
        $devolucion->firma_entrego_path = null;
    }

    if ($campo === 'psitio' && $devolucion->firma_psitio_path) {
        if (Storage::exists($devolucion->firma_psitio_path)) {
            Storage::delete($devolucion->firma_psitio_path);
        }
        $devolucion->firma_psitio_path = null;
    }

    $devolucion->save();

    return back()->with('status', 'Firma eliminada. Ahora puedes volver a firmar en sitio.');
}

}
