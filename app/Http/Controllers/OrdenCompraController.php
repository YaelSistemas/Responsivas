<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Colaborador;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Services\OrdenCompraFolioService;
use App\Models\OcLog;

class OrdenCompraController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('permission:oc.view',   only: ['index','show','pdfOpen','pdfDownload']),
            new Middleware('permission:oc.create', only: ['create','store']),
            new Middleware('permission:oc.edit',   only: ['edit','update']),
            new Middleware('permission:oc.delete', only: ['destroy']),
        ];
    }

    /** Empresa activa (multi-tenant) */
    protected function tenantId(): int
    {
        return (int) (session('empresa_activa') ?? Auth::user()->empresa_id);
    }

    /* ===================== Listado ===================== */

    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $q        = trim($request->query('q', ''));
        $perPage  = (int) $request->query('per_page', 50);
        if ($perPage <= 0)  $perPage = 10;
        if ($perPage > 100) $perPage = 100;

        $tableOC  = (new OrdenCompra)->getTable();
        $tableCol = (new Colaborador)->getTable();

        $query = OrdenCompra::with([
            'solicitante',
            'proveedor',
            'detalles:id,orden_compra_id,concepto',
            'creator:id,name',   
            'updater:id,name',   
        ])->where('empresa_tenant_id', $tenantId);

        if ($q !== '') {
            $like = "%{$q}%";

            $query->where(function ($qq) use ($like, $tableOC, $tableCol) {
                $qq->where('numero_orden', 'like', $like)
                   ->orWhere('factura', 'like', $like);

                if (Schema::hasColumn($tableOC, 'descripcion')) {
                    $qq->orWhere('descripcion', 'like', $like);
                }
                if (Schema::hasColumn($tableOC, 'proveedor')) {
                    $qq->orWhere('proveedor', 'like', $like);
                }

                $qq->orWhereHas('proveedor', function ($p) use ($like) {
                    $p->where('nombre', 'like', $like)
                      ->orWhere('rfc', 'like', $like);
                });

                $qq->orWhereHas('solicitante', function ($s) use ($like, $tableCol) {
                    $s->where(function ($w) use ($like, $tableCol) {
                        $w->where('nombre', 'like', $like);

                        foreach ([
                            'apellido', 'apellidos',
                            'apellido_paterno', 'apellido_materno',
                            'primer_apellido', 'segundo_apellido',
                        ] as $col) {
                            if (Schema::hasColumn($tableCol, $col)) {
                                $w->orWhere($col, 'like', $like);
                            }
                        }
                    });
                });

                $qq->orWhereHas('detalles', function ($d) use ($like) {
                    $d->where('concepto', 'like', $like);
                });
            });
        }

         $ocs = $query
        ->select($tableOC.'.*')
        ->addSelect(DB::raw("
            COALESCE($tableOC.seq, CAST(SUBSTRING_INDEX($tableOC.numero_orden,'-',-1) AS UNSIGNED)) as ordnum
        "))
        ->orderByDesc('ordnum')
        ->orderByDesc('fecha')
        ->paginate($perPage);

        if ($request->ajax() && $request->boolean('partial')) {
            return response()->view('oc.partials.table', ['ocs' => $ocs], 200);
        }

        return view('oc.index', [
            'ocs'     => $ocs,
            'q'       => $q,
            'perPage' => $perPage,
        ]);
    }

    /* ===================== Crear ===================== */

    public function create(OrdenCompraFolioService $folios)
    {
        $tenantId = $this->tenantId();

        // 🔹 Solo colaboradores ACTIVOS del tenant actual
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->where('activo', 1) // ← 🔸 filtro agregado
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->get(['id', 'nombre', 'apellidos']);

        // 🔹 Proveedores del tenant actual
        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'rfc', 'ciudad', 'estado']);

        // 🔹 Datos del usuario autenticado y prefijo de folio
        $user   = auth()->user();
        $prefix = $this->makePrefix($user);

        // 🔹 Alinear contador al máximo actual en base de datos
        $folios->reconcileToDbMax($tenantId);

        // 🔹 Sugerir el siguiente folio visible
        $nextSeq        = $folios->peekNext($tenantId);
        $numeroSugerido = sprintf('%s-%04d', $prefix, $nextSeq);

        return view('oc.create', compact('colaboradores', 'proveedores', 'numeroSugerido'));
    }

    public function store(Request $request, OrdenCompraFolioService $folios)
    {
        $tenantId = $this->tenantId();
        $tabla    = (new OrdenCompra)->getTable();

        // ✅ Validación cabecera (agrego iva_porcentaje)
        $data = $request->validate([
            'fecha'           => ['required', 'date'],
            'solicitante_id'  => ['required', 'exists:colaboradores,id'],
            'proveedor_id'    => ['required', 'exists:proveedores,id'],
            'descripcion'     => ['nullable', 'string'],
            'notas'           => ['nullable','string','max:2000'],
            'monto'           => ['nullable', 'numeric', 'min:0'],
            'factura'         => ['nullable', 'string', 'max:100'],
            'numero_orden'    => ['nullable', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')],
            'iva_porcentaje'  => ['nullable','numeric','min:0','max:100'], // ← nuevo
        ]);

        // Validación partidas (sin cambios funcionales)
        $request->validate([
            'items'            => ['required','array','min:1'],
            'items.*.cantidad' => ['required','numeric','min:0.001'],
            'items.*.unidad'   => ['required','string','max:50'],
            'items.*.concepto' => ['required','string','max:500'],
            'items.*.moneda'   => ['required','string','max:10'],
            'items.*.precio'   => ['required','numeric','min:0'],
            'items.*.iva_pct'  => ['nullable','numeric','min:0','max:100'], // puede venir, pero no se usa
        ]);

        $user   = auth()->user();
        $prefix = $this->makePrefix($user);

        $orden = DB::transaction(function () use ($tenantId, $tabla, $data, $request, $prefix) {

            /* ===================== BLOQUE CRÍTICO (folios) ===================== */
            $counter = DB::table('oc_counters')->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$counter) {
                DB::table('oc_counters')->insert([
                    'tenant_id'  => $tenantId,
                    'last_seq'   => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $counter = (object)['last_seq' => 0];
            }

            $maxDb = (int) DB::table($tabla)
                ->where('empresa_tenant_id', $tenantId)
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(numero_orden,'-',-1) AS UNSIGNED)) AS m")
                ->value('m');

            $currSeq = max((int)$counter->last_seq, $maxDb);
            $nextSeq = $currSeq + 1;

            $isAdmin  = auth()->user()->hasRole('Administrador') || auth()->user()->can('oc.edit_prefix');
            $override = null;
            $reqSeq   = null;

            if ($isAdmin && !empty($data['numero_orden'])) {
                $override = $data['numero_orden'];
                if (preg_match('/(\d+)$/', $override, $m)) {
                    $reqSeq = (int)$m[1];
                }
            }

            $seq     = null;
            $noOrden = null;

            if ($override !== null && $reqSeq !== null) {
                $yaExiste = DB::table($tabla)
                    ->where('empresa_tenant_id', $tenantId)
                    ->where('numero_orden', $override)
                    ->exists();
                if ($yaExiste) {
                    throw ValidationException::withMessages([
                        'numero_orden' => "El número {$override} ya está en uso por otra orden.",
                    ]);
                }

                if ($reqSeq >= $nextSeq) {
                    DB::table('oc_counters')
                        ->where('tenant_id', $tenantId)
                        ->update(['last_seq' => $reqSeq, 'updated_at' => now()]);
                    $seq     = $reqSeq;
                    $noOrden = $override;
                } else {
                    $seq     = null;
                    $noOrden = $override;
                }
            } else {
                DB::table('oc_counters')
                    ->where('tenant_id', $tenantId)
                    ->update(['last_seq' => $nextSeq, 'updated_at' => now()]);
                $seq     = $nextSeq;
                $noOrden = sprintf('%s-%04d', $prefix, $seq);
            }
            /* =================== FIN BLOQUE CRÍTICO =================== */

            // --- Cabecera
            $payload = $data;
            $payload['empresa_tenant_id'] = $tenantId;
            $payload['numero_orden']      = $noOrden;

            if (\Schema::hasColumn($tabla, 'seq')) {
                $payload['seq'] = $seq;
            }

            if (\Schema::hasColumn($tabla, 'proveedor')) {
                $prov = \App\Models\Proveedor::where('empresa_tenant_id', $tenantId)
                    ->findOrFail($payload['proveedor_id']);
                $payload['proveedor'] = $prov->nombre;
            }

            // ✅ Guardar el IVA elegido (si la columna existe)
            if (\Schema::hasColumn($tabla, 'iva_porcentaje')) {
                $payload['iva_porcentaje'] = array_key_exists('iva_porcentaje', $data)
                    ? (float)$data['iva_porcentaje']
                    : 16.0;
            }

            $payload['created_by'] = Auth::id();

            /** @var \App\Models\OrdenCompra $oc */
            $oc = \App\Models\OrdenCompra::create($payload);

            // --- Partidas
            $subtotalOC = 0.0;

            // IVA de cabecera a usar en partidas (si quieres almacenarlo por fila también)
            $ivaPctOC = array_key_exists('iva_porcentaje', $data)
                ? (float)$data['iva_porcentaje']
                : 16.0;

            // Para el log "created" (items)
            $logItems = [];

            foreach ($request->items as $row) {
                $isEmpty = !($row['cantidad'] ?? null) && !($row['precio'] ?? null) && !($row['concepto'] ?? null);
                if ($isEmpty) continue;

                $cantidad = (float)($row['cantidad'] ?? 0);
                $precio   = (float)($row['precio'] ?? 0);
                $moneda   = $row['moneda'] ?? 'MXN';

                $importe  = round($cantidad * $precio, 4);
                $subtotal = $importe;

                // Si quieres persistir IVA por fila, usa el de cabecera:
                $ivaMonto = round($subtotal * ($ivaPctOC/100), 4);
                $total    = round($subtotal + $ivaMonto, 4);

                \App\Models\OrdenCompraDetalle::create([
                    'orden_compra_id' => $oc->id,
                    'cantidad'  => $cantidad,
                    'unidad'    => $row['unidad'] ?? null,
                    'concepto'  => $row['concepto'],
                    'moneda'    => $moneda,
                    'precio'    => $precio,
                    'importe'   => $importe,
                    'iva_pct'   => $ivaPctOC,   // ← a partir del de cabecera
                    'iva_monto' => $ivaMonto,
                    'subtotal'  => $subtotal,
                    'total'     => $total,
                ]);

                $subtotalOC += $subtotal;

                // Acumular para el log
                $logItems[] = [
                    'cantidad' => $cantidad,
                    'unidad'   => $row['unidad'] ?? null,
                    'concepto' => $row['concepto'],
                    'moneda'   => $moneda,
                    'precio'   => $precio,
                    'importe'  => $importe,
                ];
            }

            // ✅ Totales en cabecera usando el IVA elegido
            $ivaMontoOC = round($subtotalOC * ($ivaPctOC/100), 4);
            $totalOC    = round($subtotalOC + $ivaMontoOC, 4);

            if (\Schema::hasColumn($tabla, 'subtotal'))   $oc->subtotal   = $subtotalOC;
            if (\Schema::hasColumn($tabla, 'iva_monto'))  $oc->iva_monto  = $ivaMontoOC;
            $oc->monto = $totalOC; // total / monto
            $oc->saveQuietly();

            /* ========= NUEVO: Log "created" con Subtotal / IVA% / IVA / Total ========= */
            try {
                $solName = $oc->solicitante
                    ? trim(($oc->solicitante->nombre ?? '').' '.($oc->solicitante->apellidos ?? ''))
                    : $oc->solicitante_id;

                \App\Models\OcLog::create([
                    'orden_compra_id' => $oc->id,
                    'user_id'         => Auth::id(),
                    'type'            => 'created',
                    'data'            => [
                        'numero_orden'   => $oc->numero_orden,
                        'fecha'          => $oc->fecha ? $oc->fecha->format('Y-m-d') : null,
                        'solicitante'    => $solName,
                        'proveedor'      => $oc->proveedor?->nombre ?? $oc->proveedor_id,
                        'descripcion'    => $oc->descripcion,
                        'iva_porcentaje' => $ivaPctOC,
                        'subtotal'       => $subtotalOC,
                        'iva'            => $ivaMontoOC,
                        'total'          => $totalOC,
                        'notas'          => $oc->notas,
                        'estado'         => $oc->estado,
                        'items'          => $logItems,
                    ],
                ]);
            } catch (\Throwable $e) {
                // Silencioso: no interrumpir creación si falla el log
            }
            /* ======== FIN NUEVO ======== */

            return $oc;
        });

        return redirect()->route('oc.show', $orden)->with('ok', 'Orden creada.');
    }

    /* ===================== Editar ===================== */

    public function edit(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $tenantId = $this->tenantId();

        // 🔹 Solo colaboradores ACTIVOS del tenant actual
        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->where('activo', 1) // ← 🔸 agregado
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->get(['id', 'nombre', 'apellidos']);

        // 🔹 Proveedores del tenant actual
        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'rfc', 'ciudad', 'estado']);

        return view('oc.edit', [
            'oc'            => $oc->load('detalles'),
            'colaboradores' => $colaboradores,
            'proveedores'   => $proveedores,
            'detalles'      => $oc->detalles,
        ]);
    }

    public function update(Request $request, OrdenCompra $oc, OrdenCompraFolioService $folios)
    {
        $this->authorizeCompany($oc);

        $tabla = (new OrdenCompra)->getTable();

        // ¿Quién puede cambiar el folio?
        $puedeCambiarFolio = auth()->user()->hasRole('Administrador')
            || auth()->user()->can('oc.edit_prefix');

        // === Validación cabecera (sin monto, lo calculamos nosotros) ===
        $data = $request->validate([
            'numero_orden'   => [$puedeCambiarFolio ? 'required' : 'sometimes', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')->ignore($oc->id)],
            'fecha'          => ['required', 'date'],
            'solicitante_id' => ['required', 'exists:colaboradores,id'],
            'proveedor_id'   => ['required', 'exists:proveedores,id'],
            'descripcion'    => ['nullable', 'string'],
            'notas'          => ['nullable','string','max:2000'],
            'factura'        => ['nullable', 'string', 'max:100'],
            // ✅ permitir cambiar el IVA de cabecera
            'iva_porcentaje' => ['nullable','numeric','min:0','max:100'],
        ]);

        // === Validación partidas ===
        $request->validate([
            'items'            => ['required','array','min:1'],
            'items.*.id'       => ['nullable','integer','min:1'],
            'items.*.cantidad' => ['required','numeric','min:0.001'],
            'items.*.unidad'   => ['required','string','max:50'],
            'items.*.concepto' => ['required','string','max:500'],
            'items.*.moneda'   => ['required','string','max:10'],
            'items.*.precio'   => ['required','numeric','min:0'],
            'items.*.iva_pct'  => ['nullable','numeric','min:0','max:100'],
        ]);

        $tenantId = $this->tenantId();

        DB::transaction(function () use ($request, $oc, $tabla, $data, $tenantId, $puedeCambiarFolio, $folios) {

            $mueveAtras = false;

            // 1) Tratamiento del cambio de folio (NO guardamos aquí; solo preparamos $data)
            if ($puedeCambiarFolio && !empty($data['numero_orden'])) {
                $requestedSeq = null;
                if (preg_match('/(\d+)$/', $data['numero_orden'], $m)) {
                    $requestedSeq = (int) $m[1];
                }

                if ($requestedSeq) {
                    $currentNext = $folios->peekNext($tenantId);

                    if ($requestedSeq >= $currentNext) {
                        $folios->bumpTo($tenantId, $requestedSeq); // mover hacia adelante
                    } else {
                        $mueveAtras = true; // reconciliar al final
                    }

                    if (\Schema::hasColumn($tabla, 'seq')) {
                        $data['seq'] = $requestedSeq;
                    }
                }
            } else {
                unset($data['numero_orden']);
            }

            // 2) Proveedor texto (si existe)
            if (\Schema::hasColumn($tabla, 'proveedor')) {
                $prov = Proveedor::find($data['proveedor_id']);
                $data['proveedor'] = $prov?->nombre ?? '';
            }

            // === IVA de CABECERA a usar en toda la edición ===
            $ivaPctOC = $request->has('iva_porcentaje') && $request->input('iva_porcentaje') !== null
                ? (float)$request->input('iva_porcentaje')
                : (is_numeric($oc->iva_porcentaje) ? (float)$oc->iva_porcentaje : 16.0);

            // si existe la columna, persistimos el IVA elegido en cabecera
            if (\Schema::hasColumn($tabla, 'iva_porcentaje')) {
                $data['iva_porcentaje'] = $ivaPctOC;
            }

            // 3) Partidas: actualizar/crear/eliminar y ACUMULAR total (SIN tocar cabecera aún)
            $existentes = $oc->detalles()->get()->keyBy('id'); // id => modelo
            $vistos = [];
            $totalOC = 0;
            $subtotalOC = 0; // ← para cabecera

            foreach ($request->items as $row) {
                $isEmpty = !($row['cantidad'] ?? null) && !($row['precio'] ?? null) && !($row['concepto'] ?? null);
                if ($isEmpty) continue;

                $payload = [
                    'cantidad'  => (float)($row['cantidad'] ?? 0),
                    'unidad'    => $row['unidad'] ?? null,
                    'concepto'  => $row['concepto'],
                    'moneda'    => $row['moneda'] ?? 'MXN',
                    'precio'    => (float)($row['precio'] ?? 0),
                ];

                $importe  = round($payload['cantidad'] * $payload['precio'], 4);
                // ✅ usar SIEMPRE el IVA de cabecera (no el de la fila)
                $ivaPct   = $ivaPctOC;
                $subtotal = $importe;
                $ivaMonto = round($subtotal * ($ivaPct/100), 4);
                $total    = round($subtotal + $ivaMonto, 4);

                $payload += [
                    'importe'   => $importe,
                    'iva_pct'   => $ivaPct,      // ← alineado con cabecera
                    'iva_monto' => $ivaMonto,
                    'subtotal'  => $subtotal,
                    'total'     => $total,
                ];

                $id = $row['id'] ?? null;

                if ($id && $existentes->has($id)) {
                    $det = $existentes[$id];

                    // Compara viejo vs nuevo con tolerancia para numéricos
                    $numFields = ['cantidad','precio','importe','iva_pct','iva_monto','subtotal','total'];
                    $changed   = false;

                    foreach ($payload as $k => $v) {
                        $old = $det->getOriginal($k);

                        if (in_array($k, $numFields, true)) {
                            $oldN = is_null($old) ? null : (float)$old;
                            $newN = is_null($v)   ? null : (float)$v;

                            if ($oldN === null && $newN === null) continue;
                            if ($oldN === null || $newN === null) { $changed = true; break; }

                            // tolerancia para redondeos (4 decimales)
                            if (abs($oldN - $newN) > 0.0001) { $changed = true; break; }
                        } else {
                            // strings: compara trimmed
                            if (trim((string)$old) !== trim((string)$v)) { $changed = true; break; }
                        }
                    }

                    if ($changed) {
                        $det->fill($payload)->save();  // solo guarda si cambió de verdad
                    } else {
                        // NO hacer touch() para no disparar eventos
                        // $det->touch();
                    }

                    $vistos[] = (int)$id;
                } else {
                    // CREATE → sí guarda (y loguea)
                    $oc->detalles()->create($payload);
                }

                $subtotalOC += $subtotal; // ← acumular para cabecera
                $totalOC    += $total;
            }

            // DELETE los que ya no vienen → disparará OcDetalleObserver::deleted
            $toDelete = $existentes->keys()->diff($vistos);
            foreach ($toDelete as $id) {
                $existentes[$id]->delete();
            }

            // 4) Guardar CABECERA una sola vez (ahora sí), incluyendo el total calculado
            //    Además: persistir subtotal e IVA de cabecera (si existen columnas)
            $ivaMontoOC = round($subtotalOC * ($ivaPctOC/100), 4);

            if (\Schema::hasColumn($tabla, 'subtotal'))  { $data['subtotal']  = $subtotalOC; }
            if (\Schema::hasColumn($tabla, 'iva_monto')) { $data['iva_monto'] = $ivaMontoOC; }

            $data['monto']      = $totalOC;
            $data['updated_by'] = Auth::id();

            $oc->fill($data);

            // ÚNICO save de cabecera en todo el flujo
            if ($oc->isDirty()) {
                $oc->save();        // ← "updated" con TODOS los cambios
            } else {
                $oc->saveQuietly(); // no registrar log si realmente no cambió nada
            }

            // === LOG: cambio de recepción ===
            if ($oc->wasChanged('recepcion')) {
                OcLog::create([
                    'orden_compra_id' => $oc->id,
                    'type'            => 'recepcion_changed',
                    'data' => [
                        'from' => $oc->getOriginal('recepcion'),
                        'to'   => $oc->recepcion,
                    ],
                    'user_id' => auth()->id(),
                ]);
            }

            // 5) Si moviste el folio hacia atrás, baja el contador al tope real (MAX(seq) en BD)
            if ($mueveAtras) {
                $folios->reconcileToDbMax($tenantId);
            }
        });

        return redirect()->route('oc.show', $oc)->with('updated', true);
    }

    /* ===================== Vistas y PDF ===================== */

    public function show(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $empresa = auth()->user()->empresa ?? null;

        return view('oc.show', compact('oc', 'empresa'));
    }

    public function destroy(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $oc->delete();

        return redirect()->route('oc.index')->with('deleted', true);
    }

    protected function authorizeCompany(OrdenCompra $oc): void
    {
        if ((int) $oc->empresa_tenant_id !== $this->tenantId()) {
            abort(403);
        }
    }

    /* ===================== Helpers ===================== */

    /** Inicial de nombre + inicial de primer apellido (prefijo visible) */
    protected function makePrefix($user): string
    {
        $nombre = trim($user->nombre ?? '');
        $iniNombre = $nombre !== '' ? mb_strtoupper(mb_substr($nombre, 0, 1)) : '';

        $primerApellido = '';
        if (!empty($user->apellido_paterno)) {
            $primerApellido = trim($user->apellido_paterno);
        } elseif (!empty($user->apellidos)) {
            $primerApellido = trim(explode(' ', trim($user->apellidos))[0] ?? '');
        } elseif (!empty($user->apellido)) {
            $primerApellido = trim(explode(' ', trim($user->apellido))[0] ?? '');
        }

        if ($primerApellido === '' && isset($user->name)) {
            $parts = preg_split('/\s+/', trim($user->name));
            if (is_array($parts) && count($parts) >= 2) {
                $primerApellido = $parts[count($parts) - 2];
                if ($iniNombre === '') {
                    $iniNombre = mb_strtoupper(mb_substr($parts[0], 0, 1));
                }
            }
        }
        if ($iniNombre === '' && isset($user->name)) {
            $iniNombre = mb_strtoupper(mb_substr($user->name, 0, 1));
        }

        $iniApellido = $primerApellido !== '' ? mb_strtoupper(mb_substr($primerApellido, 0, 1)) : 'X';

        return $iniNombre . $iniApellido;
    }

    /** (opcional) siguiente global no bloqueante por prefijo */
    protected function nextConsecutiveGlobal(string $prefix, int $tenantId): string
    {
        $tabla = (new OrdenCompra)->getTable();

        $row = DB::table($tabla)
            ->where('empresa_tenant_id', $tenantId)
            ->selectRaw("numero_orden, CAST(SUBSTRING_INDEX(numero_orden,'-',-1) AS UNSIGNED) AS suf")
            ->orderByRaw("suf DESC")
            ->first();

        $next = $row ? ((int) $row->suf + 1) : 1;

        return sprintf('%s-%04d', $prefix, $next);
    }

    public function pdfOpen(OrdenCompra $oc)
    {
        $html = view('oc.pdf_sheet', compact('oc'))->render();

        $chromePath = env('CHROME_PATH', 'C:\Program Files\Google\Chrome\Application\chrome.exe');
        if (!is_file($chromePath)) {
            $edge = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
            if (is_file($edge)) $chromePath = $edge;
        }

        $pdf = Browsershot::html($html)
            ->setChromePath($chromePath)
            ->noSandbox()
            ->showBackground()
            ->emulateMedia('screen')
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->timeout(120000)
            ->waitUntil('load')
            ->setOption('args', [
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
            ])
            ->pdf();

        $filename = 'oc-'.($oc->numero_orden ?? Str::uuid()).'.pdf';

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    public function pdfDownload(OrdenCompra $oc)
    {
        $html = view('oc.pdf_sheet', compact('oc'))->render();

        $chromePath = env('CHROME_PATH', 'C:\Program Files\Google\Chrome\Application\chrome.exe');
        if (!is_file($chromePath)) {
            $edge = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
            if (is_file($edge)) $chromePath = $edge;
        }

        $pdf = Browsershot::html($html)
            ->setChromePath($chromePath)
            ->noSandbox()
            ->showBackground()
            ->emulateMedia('screen')
            ->format('A4')
            ->margins(10,10,10,10)
            ->timeout(120000)
            ->waitUntil('load')
            ->setOption('args', [
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
            ])
            ->pdf();

        $filename = 'oc-'.($oc->numero_orden ?? Str::uuid()).'.pdf';

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /* ===== Utilidad opcional para admins ===== */
    public function adjustCounter(Request $request, OrdenCompraFolioService $folios)
    {
        $this->authorize('oc.adjust_counter');
        $data = $request->validate(['next_seq' => ['required','integer','min:1']]);
        // Queremos que la próxima sea next_seq, por eso fijamos last_seq = next_seq - 1
        $folios->setNextSeq($this->tenantId(), (int)$data['next_seq']);
        return back()->with('ok', "Siguiente consecutivo fijado a {$data['next_seq']}");
    }

    public function updateEstado(Request $request, OrdenCompra $oc)
    {
        $user = Auth::user();

        // Solo Administrador o Compras Superior pueden cambiar estado
        if (!$user->hasAnyRole(['Administrador', 'Compras Superior'])) {
            abort(403, 'No tienes permisos para cambiar el estado de una orden.');
        }

        $data = $request->validate([
            'estado' => ['required', Rule::in(OrdenCompra::ESTADOS)]
        ]);

        $oc->estado = $data['estado'];
        if (Schema::hasColumn($oc->getTable(), 'updated_by')) {
            $oc->updated_by = $user->id;
        }
        $oc->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'     => true,
                'estado' => $oc->estado,
                'label'  => $oc->estado_label,
                'class'  => $oc->estado_class,
                'msg'    => 'Estado actualizado correctamente.',
            ]);
        }

        return back()->with('updated', true);
    }

    public function updateRecepcion(Request $request, OrdenCompra $oc)
    {
        $user = Auth::user();

        // Solo Administrador o Compras Superior pueden cambiar recepción
        if (!$user->hasAnyRole(['Administrador', 'Compras Superior'])) {
            abort(403, 'No tienes permisos para cambiar la recepción de una orden.');
        }

        $data = $request->validate([
            'recepcion' => ['required', Rule::in(OrdenCompra::RECEPCIONES)],
        ]);

        $old = $oc->recepcion;   // ← Guardamos valor ANTERIOR

        $oc->recepcion = $data['recepcion'];

        if (Schema::hasColumn($oc->getTable(), 'updated_by')) {
            $oc->updated_by = $user->id;
        }

        $oc->save();

        // === LOG DE CAMBIO DE RECEPCIÓN ===
        OcLog::create([
            'orden_compra_id' => $oc->id,
            'type'            => 'recepcion_changed',
            'data' => [
                'from' => $old,
                'to'   => $oc->recepcion,
            ],
            'user_id' => auth()->id(),
        ]);

        // Respuesta AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok'        => true,
                'recepcion' => $oc->recepcion,
                'label'     => $oc->recepcion_label,
                'class'     => $oc->recepcion_class,
                'msg'       => 'Recepción actualizada correctamente.',
            ]);
        }

        return back()->with('updated', true);
    }

}
