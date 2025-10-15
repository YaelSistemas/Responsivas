<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrdenCompraFolioService
{
    // Lee el “siguiente” sin consumir
    public function peekNext(int $tenantId): int
    {
        $last = (int) DB::table('oc_counters')
            ->where('tenant_id', $tenantId)
            ->value('last_seq');

        return $last ? ($last + 1) : 1;
    }

    // Consume (incrementa) el consecutivo de forma atómica
    public function nextSeq(int $tenantId): int
    {
        return DB::transaction(function () use ($tenantId) {
            $row = DB::table('oc_counters')->lockForUpdate()->where('tenant_id', $tenantId)->first();
            if (!$row) {
                DB::table('oc_counters')->insert([
                    'tenant_id' => $tenantId,
                    'last_seq'  => 0,
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]);
                $row = (object)['last_seq' => 0];
            }

            $next = (int)$row->last_seq + 1;

            DB::table('oc_counters')
                ->where('tenant_id', $tenantId)
                ->update(['last_seq' => $next, 'updated_at' => now()]);

            return $next;
        }, 3);
    }

    // Sube el contador exactamente a $seq (la próxima será $seq+1)
    public function bumpTo(int $tenantId, int $seq): void
    {
        DB::transaction(function () use ($tenantId, $seq) {
            $curr = (int) DB::table('oc_counters')->lockForUpdate()
                ->where('tenant_id', $tenantId)->value('last_seq');

            if ($seq > $curr) {
                DB::table('oc_counters')->updateOrInsert(
                    ['tenant_id' => $tenantId],
                    ['last_seq' => $seq, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }, 3);
    }

    // ⚠️ Clave: alinear el contador al MÁXIMO visible en la tabla de OCs
    public function reconcileToDbMax(int $tenantId): void
    {
        // Toma el sufijo numérico de numero_orden (p. ej. YR-0034 → 34)
        $maxVisible = (int) DB::table('ordenes_compra')
            ->where('empresa_tenant_id', $tenantId)
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(numero_orden,'-',-1) AS UNSIGNED)) AS m")
            ->value('m');

        DB::transaction(function () use ($tenantId, $maxVisible) {
            $curr = (int) DB::table('oc_counters')->lockForUpdate()
                ->where('tenant_id', $tenantId)->value('last_seq');

            // Solo BAJA si el contador está por encima del tope real.
            if ($curr > $maxVisible) {
                DB::table('oc_counters')->updateOrInsert(
                    ['tenant_id' => $tenantId],
                    ['last_seq' => $maxVisible, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            // Si $curr < $maxVisible (poco común), también lo subimos al tope.
            if ($curr < $maxVisible) {
                DB::table('oc_counters')->updateOrInsert(
                    ['tenant_id' => $tenantId],
                    ['last_seq' => $maxVisible, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }, 3);
    }
}
