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

        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')->get();

        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')->get(['id','nombre','rfc','ciudad','estado']);

        $user   = auth()->user();
        $prefix = $this->makePrefix($user);

        // Alinear contador al tope real (permite bajar el “siguiente” si moviste uno hacia atrás en edit)
        $folios->reconcileToDbMax($tenantId);

        // Sugerido visible
        $nextSeq        = $folios->peekNext($tenantId);
        $numeroSugerido = sprintf('%s-%04d', $prefix, $nextSeq);

        return view('oc.create', compact('colaboradores', 'proveedores', 'numeroSugerido'));
    }

    public function store(Request $request, OrdenCompraFolioService $folios)
{
    $tenantId = $this->tenantId();
    $tabla    = (new OrdenCompra)->getTable();

    // Validación cabecera
    $data = $request->validate([
        'fecha'          => ['required', 'date'],
        'solicitante_id' => ['required', 'exists:colaboradores,id'],
        'proveedor_id'   => ['required', 'exists:proveedores,id'],
        'descripcion'    => ['nullable', 'string'],
        'notas' => ['nullable','string','max:2000'],
        'monto'          => ['nullable', 'numeric', 'min:0'],
        'factura'        => ['nullable', 'string', 'max:100'],
        'numero_orden'   => ['nullable', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')],
    ]);

    // Validación partidas
    $request->validate([
        'items'            => ['required','array','min:1'],
        'items.*.cantidad' => ['required','numeric','min:0.001'],
        'items.*.unidad'   => ['required','string','max:50'],
        'items.*.concepto' => ['required','string','max:500'],
        'items.*.moneda'   => ['required','string','max:10'],
        'items.*.precio'   => ['required','numeric','min:0'],
        'items.*.iva_pct'  => ['nullable','numeric','min:0','max:100'],
    ]);

    $user   = auth()->user();
    $prefix = $this->makePrefix($user);

    $orden = DB::transaction(function () use ($tenantId, $tabla, $data, $request, $prefix) {

        /* ===================== BLOQUE CRÍTICO =====================
           Evita que dos usuarios tomen el mismo folio.
           - Bloqueamos el contador
           - Reconciliamos con el máximo real en BD
           - Decidimos y consumimos el número de forma atómica
        =========================================================== */

        // 1) Bloquear (y crear si no existe)
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

        // 2) Reconciliar con el máximo real de la tabla (por si hubo ediciones hacia atrás)
        $maxDb = (int) DB::table($tabla)
            ->where('empresa_tenant_id', $tenantId)
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(numero_orden,'-',-1) AS UNSIGNED)) AS m")
            ->value('m');

        $currSeq = max((int)$counter->last_seq, $maxDb); // tope actual real
        $nextSeq = $currSeq + 1;                         // siguiente sugerido

        // 3) Resolver override (si lo hay) y CONSUMIR
        $isAdmin  = auth()->user()->hasRole('Administrador') || auth()->user()->can('oc.edit_prefix');
        $override = null;
        $reqSeq   = null;

        if ($isAdmin && !empty($data['numero_orden'])) {
            $override = $data['numero_orden'];
            if (preg_match('/(\d+)$/', $override, $m)) {
                $reqSeq = (int)$m[1];
            }
        }

        $seq     = null; // para columna seq (puede quedar null en override hacia atrás)
        $noOrden = null; // visible

        if ($override !== null && $reqSeq !== null) {
            // No permitir duplicar un número que ya exista
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
                // Override hacia ADELANTE: dejamos last_seq = reqSeq (siguiente será reqSeq+1)
                DB::table('oc_counters')
                    ->where('tenant_id', $tenantId)
                    ->update(['last_seq' => $reqSeq, 'updated_at' => now()]);
                $seq     = $reqSeq;           // alinear seq interna si existe esa columna
                $noOrden = $override;
            } else {
                // Override hacia ATRÁS: NO movemos el contador; próxima seguirá siendo $nextSeq
                $seq     = null;              // importante para no chocar a futuro
                $noOrden = $override;
                // (no tocamos last_seq aquí)
            }
        } else {
            // Sin override: consumimos $nextSeq y actualizamos last_seq = $nextSeq
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
            $payload['seq'] = $seq; // puede ser null cuando override hacia atrás
        }

        if (\Schema::hasColumn($tabla, 'proveedor')) {
            $prov = \App\Models\Proveedor::where('empresa_tenant_id', $tenantId)
                ->findOrFail($payload['proveedor_id']);
            $payload['proveedor'] = $prov->nombre;
        }

        $payload['created_by'] = Auth::id();

        /** @var \App\Models\OrdenCompra $oc */
        $oc = \App\Models\OrdenCompra::create($payload);

        // --- Partidas
        $totalOC = 0;
        foreach ($request->items as $row) {
            $isEmpty = !($row['cantidad'] ?? null) && !($row['precio'] ?? null) && !($row['concepto'] ?? null);
            if ($isEmpty) continue;

            $cantidad = (float)($row['cantidad'] ?? 0);
            $precio   = (float)($row['precio'] ?? 0);
            $moneda   = $row['moneda'] ?? 'MXN';
            $ivaPct   = strlen((string)($row['iva_pct'] ?? '')) ? (float)$row['iva_pct'] : 16;

            $importe  = round($cantidad * $precio, 4);
            $subtotal = $importe;
            $ivaMonto = round($subtotal * ($ivaPct/100), 4);
            $total    = round($subtotal + $ivaMonto, 4);

            \App\Models\OrdenCompraDetalle::create([
                'orden_compra_id' => $oc->id,
                'cantidad'  => $cantidad,
                'unidad'    => $row['unidad'] ?? null,
                'concepto'  => $row['concepto'],
                'moneda'    => $moneda,
                'precio'    => $precio,
                'importe'   => $importe,
                'iva_pct'   => $ivaPct,
                'iva_monto' => $ivaMonto,
                'subtotal'  => $subtotal,
                'total'     => $total,
            ]);

            $totalOC += $total;
        }

        // --- Total en cabecera
        $oc->monto = $totalOC;
        $oc->saveQuietly(); 

        return $oc;
    });

    return redirect()->route('oc.show', $orden)->with('ok', 'Orden creada.');
}

    /* ===================== Editar ===================== */

    public function edit(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $tenantId = $this->tenantId();

        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')->get();

        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')->get(['id','nombre','rfc','ciudad','estado']);

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

        // === Validación cabecera ===
        $data = $request->validate([
            'numero_orden'   => [$puedeCambiarFolio ? 'required' : 'sometimes', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')->ignore($oc->id)],
            'fecha'          => ['required', 'date'],
            'solicitante_id' => ['required', 'exists:colaboradores,id'],
            'proveedor_id'   => ['required', 'exists:proveedores,id'],
            'descripcion'    => ['nullable', 'string'],
            'notas' => ['nullable','string','max:2000'],
            'monto'          => ['nullable', 'numeric', 'min:0'], // se recalcula
            'factura'        => ['nullable', 'string', 'max:100'],
        ]);

        // === Validación partidas ===
        $request->validate([
            'items'            => ['required','array','min:1'],
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

            // 1) Tratamiento del cambio de folio
            if ($puedeCambiarFolio && !empty($data['numero_orden'])) {
                $requestedSeq = null;
                if (preg_match('/(\d+)$/', $data['numero_orden'], $m)) {
                    $requestedSeq = (int) $m[1];
                }

                if ($requestedSeq) {
                    $currentNext = $folios->peekNext($tenantId);

                    if ($requestedSeq >= $currentNext) {
                        // Adelante → empuja el contador para que la PRÓXIMA sea ese+1
                        $folios->bumpTo($tenantId, $requestedSeq);
                    } else {
                        // Atrás → después de guardar, bajaremos el contador al tope real
                        $mueveAtras = true;
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

            // 3) Cabecera
            $data['updated_by'] = Auth::id();
            $oc->update($data);

            // 4) Reemplazar partidas
            $oc->detalles()->delete();

            $totalOC = 0;
            foreach ($request->items as $row) {
                $isEmpty = !($row['cantidad'] ?? null) && !($row['precio'] ?? null) && !($row['concepto'] ?? null);
                if ($isEmpty) continue;

                $cantidad = (float)($row['cantidad'] ?? 0);
                $precio   = (float)($row['precio'] ?? 0);
                $moneda   = $row['moneda'] ?? 'MXN';
                $ivaPct   = strlen((string)($row['iva_pct'] ?? '')) ? (float)$row['iva_pct'] : 16;

                $importe  = round($cantidad * $precio, 4);
                $subtotal = $importe;
                $ivaMonto = round($subtotal * ($ivaPct/100), 4);
                $total    = round($subtotal + $ivaMonto, 4);

                $oc->detalles()->create([
                    'cantidad'  => $cantidad,
                    'unidad'    => $row['unidad'] ?? null,
                    'concepto'  => $row['concepto'],
                    'moneda'    => $moneda,
                    'precio'    => $precio,
                    'importe'   => $importe,
                    'iva_pct'   => $ivaPct,
                    'iva_monto' => $ivaMonto,
                    'subtotal'  => $subtotal,
                    'total'     => $total,
                ]);

                $totalOC += $total;
            }

            // 5) Total cabecera
            $oc->update(['monto' => $totalOC, 'updated_by' => Auth::id()]);

            // 6) Si moviste hacia atrás, baja el contador al tope real (MAX(seq) en BD)
            if ($mueveAtras) {
                $folios->reconcileToDbMax($tenantId);
            }
        });

        return redirect()->route('oc.index')->with('updated', true);
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
}
