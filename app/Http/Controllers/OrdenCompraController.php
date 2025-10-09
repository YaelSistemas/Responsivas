<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Colaborador;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Str;
class OrdenCompraController extends Controller
{
    /** Devuelve el tenant actual (empresa activa en sesión o la del usuario) */
    protected function tenantId(): int
    {
        return (int) (session('empresa_activa') ?? Auth::user()->empresa_id);
    }

    public function index(Request $request)
{
    $tenantId = $this->tenantId();
    $q        = trim($request->query('q', ''));
    $perPage  = (int) $request->query('per_page', 50);
    if ($perPage <= 0)  $perPage = 10;
    if ($perPage > 100) $perPage = 100;

    $tableOC   = (new OrdenCompra)->getTable();      // p.ej. 'ordenes_compra'
    $tableCol  = (new Colaborador)->getTable();      // p.ej. 'colaboradores'

    $query = OrdenCompra::with([
        'solicitante',
        'proveedor',
        'detalles:id,orden_compra_id,concepto',
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

            // 🔧 AQUÍ ESTABA EL PROBLEMA
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

    $ocs = $query->orderByDesc('fecha')->paginate($perPage);

    if (request()->ajax() && request()->boolean('partial')) {
        return response()->view('oc.partials.table', ['ocs' => $ocs], 200);
    }

    return view('oc.index', [
        'ocs'     => $ocs,
        'q'       => $q,
        'perPage' => $perPage,
    ]);
}


    public function create()
    {
        $tenantId = $this->tenantId();

        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get();

        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get(['id','nombre','rfc','ciudad','estado']);

        $user           = auth()->user();
        $prefix         = $this->makePrefix($user);
        $numeroSugerido = $this->nextConsecutiveGlobal($prefix, $tenantId);

        return view('oc.create', compact('colaboradores', 'proveedores', 'numeroSugerido'));
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenantId();
        $tabla    = (new OrdenCompra)->getTable();

        // Validación cabecera
        $data = $request->validate([
            'numero_orden'   => ['nullable', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')],
            'fecha'          => ['required', 'date'],
            'solicitante_id' => ['required', 'exists:colaboradores,id'],
            'proveedor_id'   => ['required', 'exists:proveedores,id'],
            'descripcion'    => ['nullable', 'string'],
            'monto'          => ['nullable', 'numeric', 'min:0'], // se recalcula
            'factura'        => ['nullable', 'string', 'max:100'],
        ]);

        // Validación partidas (nombres según tus modelos)
        $request->validate([
            'items'            => ['required','array','min:1'],
            'items.*.cantidad' => ['required','numeric','min:0.001'],
            'items.*.unidad'   => ['nullable','string','max:50'],
            'items.*.concepto' => ['required','string','max:500'],
            'items.*.moneda'   => ['required','string','max:10'],
            'items.*.precio'   => ['required','numeric','min:0'],
            'items.*.iva_pct'  => ['nullable','numeric','min:0','max:100'],
        ]);

        // Genera consecutivo si no viene
        if (empty($data['numero_orden'])) {
            $user   = auth()->user();
            $prefix = $this->makePrefix($user);

            $data['numero_orden'] = DB::transaction(function () use ($prefix, $tenantId, $tabla) {
                $row = DB::table($tabla)
                    ->where('empresa_tenant_id', $tenantId)
                    ->selectRaw("numero_orden, CAST(SUBSTRING_INDEX(numero_orden,'-',-1) AS UNSIGNED) AS suf")
                    ->orderByRaw("suf DESC")
                    ->lockForUpdate()
                    ->first();

                $next = $row ? ((int) $row->suf + 1) : 1;
                $candidate = sprintf('%s-%04d', $prefix, $next);

                while (
                    DB::table($tabla)
                      ->where('empresa_tenant_id', $tenantId)
                      ->where('numero_orden', $candidate)
                      ->exists()
                ) {
                    $next++;
                    $candidate = sprintf('%s-%04d', $prefix, $next);
                }

                return $candidate;
            });
        }

        // Guarda cabecera + detalles
        DB::transaction(function () use ($request, $tenantId, $tabla, $data) {
            $payload = $data;
            $payload['empresa_tenant_id'] = $tenantId;

            // Sincroniza columna 'proveedor' (texto) si existe
            if (\Schema::hasColumn($tabla, 'proveedor')) {
                $prov = Proveedor::where('empresa_tenant_id', $tenantId)
                    ->findOrFail($payload['proveedor_id']);
                $payload['proveedor'] = $prov->nombre;
            }

            /** @var \App\Models\OrdenCompra $oc */
            $oc = OrdenCompra::create($payload);

            // Partidas
            $totalOC = 0;
            foreach ($request->items as $row) {
                // Salta filas completamente vacías
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

                OrdenCompraDetalle::create([
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

            // Actualiza monto total de la OC con la suma de partidas
            $oc->update(['monto' => $totalOC]);
        });

        return redirect()->route('oc.index')->with('created', true);
    }

    public function edit(OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);

        $tenantId = $this->tenantId();

        $colaboradores = Colaborador::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get();

        $proveedores = Proveedor::where('empresa_tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get(['id','nombre','rfc','ciudad','estado']);

        // Cargamos detalles para el prefill de la vista
        return view('oc.edit', [
            'oc'            => $oc->load('detalles'),
            'colaboradores' => $colaboradores,
            'proveedores'   => $proveedores,
            'detalles'      => $oc->detalles,
        ]);
    }

    public function update(Request $request, OrdenCompra $oc)
    {
        $this->authorizeCompany($oc);
        $tabla = (new OrdenCompra)->getTable();

        // Validación cabecera
        $data = $request->validate([
            'numero_orden'   => ['required', 'string', 'max:50', Rule::unique($tabla, 'numero_orden')->ignore($oc->id)],
            'fecha'          => ['required', 'date'],
            'solicitante_id' => ['required', 'exists:colaboradores,id'],
            'proveedor_id'   => ['required', 'exists:proveedores,id'],
            'descripcion'    => ['nullable', 'string'],
            'monto'          => ['nullable', 'numeric', 'min:0'], // se recalcula
            'factura'        => ['nullable', 'string', 'max:100'],
        ]);

        // Validación partidas
        $request->validate([
            'items'            => ['required','array','min:1'],
            'items.*.cantidad' => ['required','numeric','min:0.001'],
            'items.*.unidad'   => ['nullable','string','max:50'],
            'items.*.concepto' => ['required','string','max:500'],
            'items.*.moneda'   => ['required','string','max:10'],
            'items.*.precio'   => ['required','numeric','min:0'],
            'items.*.iva_pct'  => ['nullable','numeric','min:0','max:100'],
        ]);

        DB::transaction(function () use ($request, $oc, $tabla, $data) {
            // 1) cabecera
            $payload = $data;

            // sincroniza columna proveedor (texto) si existe
            if (\Schema::hasColumn($tabla, 'proveedor')) {
                $prov = Proveedor::find($payload['proveedor_id']);
                $payload['proveedor'] = $prov?->nombre ?? '';
            }

            $oc->update($payload);

            // 2) partidas: borramos y reinsertamos (simple y seguro)
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

            // 3) actualiza total de cabecera
            $oc->update(['monto' => $totalOC]);
        });

        return redirect()->route('oc.index')->with('updated', true);
    }

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

    /** Asegura que la OC pertenece a la empresa del usuario */
    protected function authorizeCompany(OrdenCompra $oc): void
    {
        if ((int) $oc->empresa_tenant_id !== $this->tenantId()) {
            abort(403);
        }
    }

    /* ===================== Helpers ===================== */

    /** Inicial del nombre + inicial del primer apellido */
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

    /** Siguiente consecutivo global por tenant (no bloqueante) */
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

    public function pdfOpen(\App\Models\OrdenCompra $oc)
{
    // 1) Renderizar SOLO la hoja (parcial) con recursos enlined
    $html = view('oc.pdf_sheet', compact('oc'))->render();

    // 2) Paths a Chrome/Edge (ajusta si usas Edge)
    $chromePath = env('CHROME_PATH', 'C:\Program Files\Google\Chrome\Application\chrome.exe');
    if (!is_file($chromePath)) {
        // fallback a Edge si no hay Chrome
        $edge = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
        if (is_file($edge)) $chromePath = $edge;
    }

    // 3) Render con opciones robustas
    $pdf = Browsershot::html($html)
        ->setChromePath($chromePath)
        ->noSandbox()                       // importante en Windows
        ->showBackground()                  // respeta fondos/bordes
        ->emulateMedia('screen')
        ->format('A4')
        ->margins(10, 10, 10, 10)          // mm
        ->timeout(120000)                   // 120s por si está lento la 1ª vez
        ->waitUntil('load')                 // evita networkidle
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

public function pdfDownload(\App\Models\OrdenCompra $oc)
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


}
