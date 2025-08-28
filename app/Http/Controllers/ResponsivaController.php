<?php

namespace App\Http\Controllers;

use App\Models\Responsiva;
use App\Models\ResponsivaDetalle;
use App\Models\ProductoSerie;
use App\Models\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResponsivaController extends Controller
{
    // ===================== INDEX (con buscador, per_page y partial) =====================
    public function index(Request $request)
    {
        $tenantId = (int) session('empresa_activa', auth()->user()?->empresa_id);
        $perPage  = (int) $request->query('per_page', 10);
        $q        = trim((string) $request->query('q', ''));

        $rows = Responsiva::query()
            ->with(['colaborador:id,nombre', 'usuario:id,name'])
            ->withCount('detalles')
            // multi-tenant por series ligadas
            ->whereHas('detalles.serie', fn($s) => $s->where('empresa_tenant_id', $tenantId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('folio', 'like', "%{$q}%")
                      ->orWhere('fecha_entrega', 'like', "%{$q}%")
                      ->orWhereHas('colaborador', fn($c) => $c->where('nombre', 'like', "%{$q}%"))
                      ->orWhereHas('usuario', fn($u) => $u->where('name', 'like', "%{$q}%"));
                });
            })
            ->latest('fecha_entrega')
            ->paginate($perPage)
            ->withQueryString();

        if ($request->boolean('partial')) {
            return view('responsivas.partials.table', compact('rows'))->render();
        }

        return view('responsivas.index', compact('rows', 'perPage', 'q'));
    }

    // ===================== CREATE =====================
    public function create()
{
    $tenantId = (int) session('empresa_activa', auth()->user()?->empresa_id);

    // Colaboradores (detección de columna multi-tenant)
    $colabQ = \App\Models\Colaborador::query()->orderBy('nombre')->select(['id','nombre']);
    if (\Illuminate\Support\Facades\Schema::hasColumn('colaboradores','empresa_id')) {
        $colabQ->where('empresa_id', $tenantId);
    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('colaboradores','empresa_tenant_id')) {
        $colabQ->where('empresa_tenant_id', $tenantId);
    }
    $colaboradores = $colabQ->get();

    // Series disponibles del tenant
    $series = \App\Models\ProductoSerie::deEmpresa($tenantId)
        ->disponibles()
        ->with('producto:id,nombre,marca,modelo,tipo,descripcion,especificaciones')
        ->orderBy('producto_id')
        ->get(['id','producto_id','serie','estado']);

    // Usuarios con rol Administrador
    $admins = \App\Models\User::role('Administrador')
        ->orderBy('name')
        ->get(['id','name']);

    return view('responsivas.create', compact('colaboradores','series','admins'));
}

public function store(\Illuminate\Http\Request $req)
{
    $tenantId = (int) session('empresa_activa', auth()->user()?->empresa_id);

    // admins válidos (para validar entregó / autorizó)
    $adminIds = \App\Models\User::role('Administrador')->pluck('id')->all();

    // Regla exists dinámica para colaboradores (multi-tenant)
    $colExists = \Illuminate\Validation\Rule::exists('colaboradores','id');
    if (\Illuminate\Support\Facades\Schema::hasColumn('colaboradores','empresa_id')) {
        $colExists = $colExists->where('empresa_id', $tenantId);
    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('colaboradores','empresa_tenant_id')) {
        $colExists = $colExists->where('empresa_tenant_id', $tenantId);
    }

    // Validación
    $req->validate([
        'motivo_entrega'        => ['required', \Illuminate\Validation\Rule::in(['asignacion','prestamo_provisional'])],
        'colaborador_id'        => ['required', $colExists],
        'recibi_colaborador_id' => ['nullable', $colExists],
        'entrego_user_id'       => ['required', \Illuminate\Validation\Rule::in($adminIds)],
        'autoriza_user_id'      => ['nullable', \Illuminate\Validation\Rule::in($adminIds)],
        'series_ids'            => ['required','array','min:1'],
        'series_ids.*'          => ['integer', \Illuminate\Validation\Rule::exists('producto_series','id')->where('empresa_tenant_id', $tenantId)],
        'observaciones'         => ['nullable','string','max:2000'],
    ]);

    $folio = $this->nextFolio();

    \Illuminate\Support\Facades\DB::transaction(function() use ($req, $folio, $tenantId) {
        // Creamos la responsiva (user_id = ENTREGÓ)
        $resp = \App\Models\Responsiva::create([
            'folio'                 => $folio,
            'colaborador_id'        => $req->colaborador_id,
            'recibi_colaborador_id' => $req->recibi_colaborador_id ?: $req->colaborador_id,
            'user_id'               => $req->entrego_user_id,      // ENTREGÓ (debe ser admin)
            'autoriza_user_id'      => $req->autoriza_user_id,     // puede ser null, pero admin si viene
            'motivo_entrega'        => $req->motivo_entrega,
            'fecha_entrega'         => now()->toDateString(),
            'observaciones'         => $req->observaciones,
        ]);

        // Bloquear y validar series del tenant
        $series = \App\Models\ProductoSerie::deEmpresa($tenantId)
            ->whereIn('id', $req->series_ids)
            ->lockForUpdate()
            ->get(['id','producto_id','serie','estado']);

        if ($series->count() !== count($req->series_ids)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'series_ids' => 'Algunas series no existen o no pertenecen a la empresa activa.',
            ]);
        }

        $disponible = defined(\App\Models\ProductoSerie::class.'::ESTADO_DISPONIBLE')
            ? \App\Models\ProductoSerie::ESTADO_DISPONIBLE : 'disponible';

        foreach ($series as $s) {
            if ($s->estado !== $disponible) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'series_ids' => "La serie {$s->serie} ya no está disponible.",
                ]);
            }
        }

        // Detalles + cambio de estado de series
        $asignado = defined(\App\Models\ProductoSerie::class.'::ESTADO_ASIGNADO')
            ? \App\Models\ProductoSerie::ESTADO_ASIGNADO : 'asignado';

        foreach ($series as $s) {
            \App\Models\ResponsivaDetalle::create([
                'responsiva_id'     => $resp->id,
                'producto_id'       => $s->producto_id,
                'producto_serie_id' => $s->id,
            ]);

            $s->update([
                'estado'                    => $asignado,
                'asignado_en_responsiva_id' => $resp->id,
            ]);
        }
    });

    // Redirige al SHOW (como ya hiciste antes)
    $created = \App\Models\Responsiva::latest('id')->first();
    return redirect()->route('responsivas.show', $created)->with('ok', 'Responsiva creada.');
}



    // ===================== SHOW =====================
    public function show(Responsiva $responsiva)
    {
        $responsiva->load([
            'colaborador', 'usuario',
            'detalles.producto', 'detalles.serie'
        ]);

        return view('responsivas.show', compact('responsiva'));
    }

    // ===================== FOLIO =====================
    private function nextFolio(): string
    {
        // Folio: R-{AÑO}-{consecutivo}
        $year = now()->format('Y');
        $lastIdThisYear = Responsiva::whereYear('created_at', $year)->max('id') ?? 0;
        $num = str_pad($lastIdThisYear + 1, 4, '0', STR_PAD_LEFT);
        return "R-{$year}-{$num}";
    }
}
