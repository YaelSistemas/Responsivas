<?php

namespace App\Http\Controllers;

use App\Models\Responsiva;
use App\Models\ResponsivaDetalle;
use App\Models\ProductoSerie;
use App\Models\Colaborador;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class ResponsivaController extends Controller
{
    /* ===================== Helpers ===================== */
    private function tenantId(): int
    {
        return (int) session('empresa_activa', auth()->user()?->empresa_id);
    }

    /** Devuelve un ID de admin “por defecto” (el primero que encuentre) */
    private function defaultAdminId(): ?int
    {
        return (int) User::role('Administrador')->orderBy('id')->value('id');
    }

    /* ===================== INDEX (buscador + partial) ===================== */
    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $perPage  = (int) $request->query('per_page', 10);
        $q        = trim((string) $request->query('q', ''));

        $colSel = ['id', 'nombre'];
        foreach ([
            'apellidos', 'apellido', 'apellido_paterno', 'apellido_materno',
            'primer_apellido', 'segundo_apellido',
            'area_id', 'departamento_id', 'sede_id', 'unidad_servicio_id'
        ] as $c) {
            if (Schema::hasColumn('colaboradores', $c)) $colSel[] = $c;
        }

        $with = [
            'usuario:id,name',
            'colaborador' => function ($q) use ($colSel) {
                $q->select($colSel);
            },
        ];
        if (method_exists(Colaborador::class, 'area'))         $with[] = 'colaborador.area';
        if (method_exists(Colaborador::class, 'departamento')) $with[] = 'colaborador.departamento';
        if (method_exists(Colaborador::class, 'sede'))         $with[] = 'colaborador.sede';

        $rows = Responsiva::query()
            ->with($with)
            ->withCount('detalles')
            ->whereHas('detalles.serie', fn($s) => $s->where('empresa_tenant_id', $tenantId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('folio', 'like', "%{$q}%")
                      ->orWhere('fecha_entrega', 'like', "%{$q}%")
                      ->orWhereHas('colaborador', function ($c) use ($q) {
                          $c->where('nombre', 'like', "%{$q}%");
                          foreach ([
                              'apellidos', 'apellido', 'apellido_paterno', 'apellido_materno',
                              'primer_apellido', 'segundo_apellido'
                          ] as $col) {
                              if (Schema::hasColumn('colaboradores', $col)) {
                                  $c->orWhere($col, 'like', "%{$q}%");
                              }
                          }
                      })
                      ->orWhereHas('usuario', fn($u) => $u->where('name', 'like', "%{$q}%"));
                });
            })
            ->latest('fecha_entrega')
            ->paginate($perPage)
            ->withQueryString();

        return $request->boolean('partial')
            ? view('responsivas.partials.table', compact('rows'))->render()
            : view('responsivas.index', compact('rows', 'perPage', 'q'));
    }

    /* ===================== CREATE ===================== */
    public function create()
    {
        $tenantId = $this->tenantId();

        $colabQ = Colaborador::query()
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->select(['id','nombre','apellidos']);
        if (Schema::hasColumn('colaboradores','empresa_id')) {
            $colabQ->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores','empresa_tenant_id')) {
            $colabQ->where('empresa_tenant_id', $tenantId);
        }
        $colaboradores = $colabQ->get();

        $series = ProductoSerie::deEmpresa($tenantId)
            ->disponibles()
            ->with('producto:id,nombre,marca,modelo,tipo,descripcion,especificaciones')
            ->orderBy('producto_id')
            ->get(['id','producto_id','serie','estado','especificaciones']);

        $admins = User::role('Administrador')->orderBy('name')->get(['id','name']);

        return view('responsivas.create', compact('colaboradores','series','admins'));
    }

    /* ===================== STORE ===================== */
    public function store(Request $req)
    {
        $tenantId = $this->tenantId();

        $adminIds  = User::role('Administrador')->pluck('id')->all();
        $colExists = Rule::exists('colaboradores','id');
        if (Schema::hasColumn('colaboradores','empresa_id')) {
            $colExists = $colExists->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores','empresa_tenant_id')) {
            $colExists = $colExists->where('empresa_tenant_id', $tenantId);
        }

        $req->validate([
            'motivo_entrega'        => ['required', Rule::in(['asignacion','prestamo_provisional'])],
            'colaborador_id'        => ['required', $colExists],
            'recibi_colaborador_id' => ['nullable', $colExists],

            // ahora son opcionales: se pondrán por defecto si vienen vacíos
            'entrego_user_id'       => ['nullable', Rule::in($adminIds)],
            'autoriza_user_id'      => ['nullable', Rule::in($adminIds)],

            'series_ids'            => ['required','array','min:1'],
            'series_ids.*'          => ['integer', Rule::exists('producto_series','id')->where('empresa_tenant_id', $tenantId)],
            'observaciones'         => ['nullable','string','max:2000'],
            'fecha_solicitud'       => ['nullable','date'],
            'fecha_entrega'         => ['nullable','date'],
        ]);

        DB::transaction(function() use ($req, $tenantId) {

            $folio = $this->nextFolio($tenantId);

            // defaults para firmas
            $entregoId = $req->entrego_user_id
                ?: (auth()->user()?->hasRole('Administrador') ? auth()->id() : $this->defaultAdminId());

            // puedes fijar un autorizador por config/app.php => 'responsivas_autoriza_user_id'
            $autorizaFijo = (int) config('app.responsivas_autoriza_user_id', 0);
            $autorizaId   = $req->autoriza_user_id ?: ($autorizaFijo ?: $this->defaultAdminId());

            $resp = Responsiva::create([
                'empresa_tenant_id'     => $tenantId,
                'folio'                 => $folio,
                'colaborador_id'        => $req->colaborador_id,
                'recibi_colaborador_id' => $req->recibi_colaborador_id ?: $req->colaborador_id,
                'user_id'               => $entregoId,          // Entregó (auto)
                'autoriza_user_id'      => $autorizaId,         // Autorizó (auto)
                'motivo_entrega'        => $req->motivo_entrega,
                'fecha_solicitud'       => $req->fecha_solicitud,
                'fecha_entrega'         => $req->fecha_entrega ?: now()->toDateString(),
                'observaciones'         => $req->observaciones,
            ]);

            $series = ProductoSerie::deEmpresa($tenantId)
                ->whereIn('id', $req->series_ids)
                ->lockForUpdate()
                ->get(['id','producto_id','serie','estado']);

            if ($series->count() !== count($req->series_ids)) {
                throw ValidationException::withMessages([
                    'series_ids' => 'Algunas series no existen o no pertenecen a la empresa activa.',
                ]);
            }

            $disponible = defined(ProductoSerie::class.'::ESTADO_DISPONIBLE') ? ProductoSerie::ESTADO_DISPONIBLE : 'disponible';
            $asignado   = defined(ProductoSerie::class.'::ESTADO_ASIGNADO')   ? ProductoSerie::ESTADO_ASIGNADO   : 'asignado';

            foreach ($series as $s) {
                if ($s->estado !== $disponible) {
                    throw ValidationException::withMessages([
                        'series_ids' => "La serie {$s->serie} ya no está disponible.",
                    ]);
                }
            }

            foreach ($series as $s) {
                ResponsivaDetalle::create([
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

        $created = Responsiva::latest('id')->first();
        return redirect()->route('responsivas.show', $created)->with('ok', 'Responsiva creada.');
    }

    /* ===================== EDIT ===================== */
    public function edit(Responsiva $responsiva)
    {
        $tenantId = $this->tenantId();

        $responsiva->load(['detalles.producto','detalles.serie','colaborador']);

        $colabQ = Colaborador::query()
            ->orderBy('nombre')
            ->orderBy('apellidos')
            ->select(['id','nombre','apellidos']);
        if (Schema::hasColumn('colaboradores','empresa_id')) {
            $colabQ->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores','empresa_tenant_id')) {
            $colabQ->where('empresa_tenant_id', $tenantId);
        }
        $colaboradores = $colabQ->get();

        $seriesDisponibles = ProductoSerie::deEmpresa($tenantId)
            ->disponibles()
            ->with('producto:id,nombre,marca,modelo,tipo,descripcion,especificaciones')
            ->orderBy('producto_id')
            ->get(['id','producto_id','serie','estado']);

        $idsActuales = $responsiva->detalles->pluck('producto_serie_id')->all();

        $misSeries = ProductoSerie::deEmpresa($tenantId)
            ->whereIn('id', $idsActuales)
            ->with('producto:id,nombre,marca,modelo,tipo,descripcion,especificaciones')
            ->orderBy('producto_id')
            ->get(['id','producto_id','serie','estado']);

        $series = $seriesDisponibles->concat($misSeries)->unique('id')->values();

        $admins = User::role('Administrador')->orderBy('name')->get(['id','name']);

        $selectedSeries = $misSeries->pluck('id')->all();

        return view('responsivas.edit', compact(
            'responsiva','colaboradores','series','admins','selectedSeries'
        ));
    }

    /* ===================== UPDATE ===================== */
    public function update(Request $req, Responsiva $responsiva)
    {
        $tenantId = $this->tenantId();

        $adminIds  = User::role('Administrador')->pluck('id')->all();
        $colExists = Rule::exists('colaboradores','id');
        if (Schema::hasColumn('colaboradores','empresa_id')) {
            $colExists = $colExists->where('empresa_id', $tenantId);
        } elseif (Schema::hasColumn('colaboradores','empresa_tenant_id')) {
            $colExists = $colExists->where('empresa_tenant_id', $tenantId);
        }

        $req->validate([
            'motivo_entrega'        => ['required', Rule::in(['asignacion','prestamo_provisional'])],
            'colaborador_id'        => ['required', $colExists],
            'recibi_colaborador_id' => ['nullable', $colExists],
            'entrego_user_id'       => ['required', Rule::in($adminIds)],
            'autoriza_user_id'      => ['nullable', Rule::in($adminIds)],
            'series_ids'            => ['required','array','min:1'],
            'series_ids.*'          => ['integer', Rule::exists('producto_series','id')->where('empresa_tenant_id', $tenantId)],
            'fecha_solicitud'       => ['nullable','date'],
            'fecha_entrega'         => ['required','date'],
            'observaciones'         => ['nullable','string','max:2000'],
        ]);

        DB::transaction(function() use ($req, $responsiva, $tenantId) {
            $actuales = $responsiva->detalles()->pluck('producto_serie_id')->all();
            $nuevas   = $req->input('series_ids', []);

            $toAdd    = array_values(array_diff($nuevas,   $actuales));
            $toRemove = array_values(array_diff($actuales, $nuevas));

            $disponible = defined(ProductoSerie::class.'::ESTADO_DISPONIBLE') ? ProductoSerie::ESTADO_DISPONIBLE : 'disponible';
            $asignado   = defined(ProductoSerie::class.'::ESTADO_ASIGNADO')   ? ProductoSerie::ESTADO_ASIGNADO   : 'asignado';

            if ($toAdd) {
                $seriesAdd = ProductoSerie::deEmpresa($tenantId)
                    ->whereIn('id',$toAdd)->lockForUpdate()->get(['id','producto_id','serie','estado']);

                foreach ($seriesAdd as $s) {
                    if ($s->estado !== $disponible) {
                        throw ValidationException::withMessages([
                            'series_ids' => "La serie {$s->serie} no está disponible.",
                        ]);
                    }
                }
                foreach ($seriesAdd as $s) {
                    ResponsivaDetalle::create([
                        'responsiva_id'     => $responsiva->id,
                        'producto_id'       => $s->producto_id,
                        'producto_serie_id' => $s->id,
                    ]);
                    $s->update([
                        'estado' => $asignado,
                        'asignado_en_responsiva_id' => $responsiva->id,
                    ]);
                }
            }

            if ($toRemove) {
                $seriesRem = ProductoSerie::deEmpresa($tenantId)
                    ->whereIn('id',$toRemove)->lockForUpdate()->get(['id']);

                foreach ($seriesRem as $s) {
                    $s->update([
                        'estado' => $disponible,
                        'asignado_en_responsiva_id' => null,
                    ]);
                }

                ResponsivaDetalle::where('responsiva_id', $responsiva->id)
                    ->whereIn('producto_serie_id', $toRemove)
                    ->delete();
            }

            $responsiva->update([
                'motivo_entrega'        => $req->motivo_entrega,
                'colaborador_id'        => $req->colaborador_id,
                'recibi_colaborador_id' => $req->recibi_colaborador_id ?: $req->colaborador_id,
                'user_id'               => $req->entrego_user_id,
                'autoriza_user_id'      => $req->autoriza_user_id,
                'fecha_solicitud'       => $req->fecha_solicitud,
                'fecha_entrega'         => $req->fecha_entrega,
                'observaciones'         => $req->observaciones,
            ]);
        });

        return redirect()->route('responsivas.show', $responsiva)->with('updated', 'Responsiva actualizada.');
    }

    /* ===================== SHOW ===================== */
    public function show(Responsiva $responsiva)
    {
        $responsiva->load([
            'colaborador', 'usuario',
            'entrego', 'autoriza',            // 👈 para que la vista pueda mostrar firmas/nombres sin N+1
            'detalles.producto', 'detalles.serie',
        ]);

        return view('responsivas.show', compact('responsiva'));
    }

    /* ===================== FOLIO: OES-00001 por tenant ===================== */
    private function nextFolio(int $tenantId): string
    {
        // Debe llamarse DENTRO de una transacción.
        $last = Responsiva::where('empresa_tenant_id', $tenantId)
            ->where('folio', 'like', 'OES-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('folio');

        $n = 1;
        if ($last && preg_match('/^OES-(\d{5,})$/', $last, $m)) {
            $n = (int)$m[1] + 1;
        }

        return 'OES-'.str_pad((string)$n, 5, '0', STR_PAD_LEFT);
    }

    /* ===================== PDF ===================== */
    public function pdf(Responsiva $responsiva)
    {
        $pdf = Pdf::loadView('responsivas.pdf', compact('responsiva'))
                  ->setPaper('a4', 'portrait')
                  ->setOptions(['isRemoteEnabled' => true]);

        return $pdf->stream("responsiva-{$responsiva->folio}.pdf");
    }

    /* ===================== DESTROY ===================== */
    public function destroy(Responsiva $responsiva)
    {
        $tenantId = $this->tenantId();

        DB::transaction(function () use ($responsiva, $tenantId) {
            $serieIds = $responsiva->detalles()->pluck('producto_serie_id')->all();

            if (!empty($serieIds)) {
                $disponible = defined(ProductoSerie::class.'::ESTADO_DISPONIBLE') ? ProductoSerie::ESTADO_DISPONIBLE : 'disponible';

                $series = ProductoSerie::deEmpresa($tenantId)
                    ->whereIn('id', $serieIds)
                    ->lockForUpdate()
                    ->get(['id','estado','asignado_en_responsiva_id']);

                foreach ($series as $s) {
                    $s->estado = $disponible;
                    $s->asignado_en_responsiva_id = null;
                    $s->save();
                }
            }

            $responsiva->detalles()->delete();
            $responsiva->delete();
        });

        return redirect()
            ->route('responsivas.index')
            ->with('deleted', 'Responsiva eliminada. Los equipos quedaron disponibles.');
    }
}
