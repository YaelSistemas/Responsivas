<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {

            // ✅ NUEVOS CAMPOS (NO tocamos folio existente)
            if (!Schema::hasColumn('responsivas', 'tipo_documento')) {
                $table->string('tipo_documento', 30)->nullable()->after('folio');
            }

            if (!Schema::hasColumn('responsivas', 'folio_tipo')) {
                $table->unsignedInteger('folio_tipo')->nullable()->after('tipo_documento');
            }
        });

        /**
         * ✅ Backfill:
         * - folio = "OES-00020"  => tipo_documento = "OES", folio_tipo = 20
         * - folio = "CEL-00001"  => tipo_documento = "CEL", folio_tipo = 1
         * - folio numérico "20"  => tipo_documento = "general", folio_tipo = 20
         */
        DB::statement("
            UPDATE responsivas
            SET
              tipo_documento = CASE
                WHEN folio LIKE '%-%' THEN SUBSTRING_INDEX(folio, '-', 1)
                WHEN folio REGEXP '^[0-9]+$' THEN 'general'
                ELSE 'general'
              END,
              folio_tipo = CASE
                WHEN folio LIKE '%-%' THEN CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)
                WHEN folio REGEXP '^[0-9]+$' THEN CAST(folio AS UNSIGNED)
                ELSE NULL
              END
            WHERE tipo_documento IS NULL OR folio_tipo IS NULL
        ");

        // ✅ Asegura que no quede NULL en tipo_documento por si hay folios raros
        DB::table('responsivas')
            ->whereNull('tipo_documento')
            ->update(['tipo_documento' => 'general']);

        // ✅ Índice único empresa + tipo + consecutivo
        Schema::table('responsivas', function (Blueprint $table) {
            $table->unique(
                ['empresa_tenant_id', 'tipo_documento', 'folio_tipo'],
                'resp_folio_tipo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {

            try {
                $table->dropUnique('resp_folio_tipo_unique');
            } catch (\Throwable $e) {
                // noop
            }

            if (Schema::hasColumn('responsivas', 'folio_tipo')) {
                $table->dropColumn('folio_tipo');
            }

            if (Schema::hasColumn('responsivas', 'tipo_documento')) {
                $table->dropColumn('tipo_documento');
            }
        });
    }
};
