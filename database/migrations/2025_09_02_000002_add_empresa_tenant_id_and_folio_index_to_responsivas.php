<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Agregar columnas si aún no existen
        Schema::table('responsivas', function (Blueprint $table) {
            if (!Schema::hasColumn('responsivas', 'empresa_tenant_id')) {
                $table->unsignedBigInteger('empresa_tenant_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('responsivas', 'folio')) {
                $table->string('folio', 64)->nullable()->after('empresa_tenant_id');
            }
        });

        // 2) Backfill seguro (sin asumir nombres de columnas)
        // 2a) Desde responsivas.empresa_id si existe
        if (Schema::hasColumn('responsivas', 'empresa_id')) {
            DB::statement("
                UPDATE responsivas
                SET empresa_tenant_id = COALESCE(empresa_tenant_id, empresa_id)
                WHERE empresa_tenant_id IS NULL
            ");
        }

        // 2b) Desde colaboradores.<empresa_tenant_id|empresa_id> si existen
        if (Schema::hasTable('colaboradores')) {
            if (Schema::hasColumn('colaboradores', 'empresa_tenant_id')) {
                DB::statement("
                    UPDATE responsivas r
                    LEFT JOIN colaboradores c ON c.id = r.colaborador_id
                    SET r.empresa_tenant_id = COALESCE(r.empresa_tenant_id, c.empresa_tenant_id)
                    WHERE r.empresa_tenant_id IS NULL
                ");
            } elseif (Schema::hasColumn('colaboradores', 'empresa_id')) {
                DB::statement("
                    UPDATE responsivas r
                    LEFT JOIN colaboradores c ON c.id = r.colaborador_id
                    SET r.empresa_tenant_id = COALESCE(r.empresa_tenant_id, c.empresa_id)
                    WHERE r.empresa_tenant_id IS NULL
                ");
            }
        }

        // 3) FK y UNIQUE (tolerantes si ya existen)
        try {
            Schema::table('responsivas', function (Blueprint $table) {
                // La columna puede seguir nullable si aún quedan nulls; está bien.
                $table->foreign('empresa_tenant_id', 'responsivas_empresa_tenant_id_foreign')
                    ->references('id')->on('empresas')
                    ->onUpdate('cascade')->onDelete('restrict');
            });
        } catch (\Throwable $e) { /* ya existe */ }

        try {
            Schema::table('responsivas', function (Blueprint $table) {
                $table->unique(['empresa_tenant_id', 'folio'], 'resp_unique_folio_per_tenant');
            });
        } catch (\Throwable $e) { /* ya existe */ }

        // 4) (Opcional) Si estás seguro que NO quedan NULL, puedes volverla NOT NULL:
        // if (DB::table('responsivas')->whereNull('empresa_tenant_id')->doesntExist()) {
        //     Schema::table('responsivas', fn (Blueprint $t) => $t->unsignedBigInteger('empresa_tenant_id')->nullable(false)->change());
        // }
    }

    public function down(): void
    {
        // Quitar índices/llaves con tolerancia
        try {
            Schema::table('responsivas', function (Blueprint $table) {
                $table->dropUnique('resp_unique_folio_per_tenant');
            });
        } catch (\Throwable $e) {}
        try {
            Schema::table('responsivas', function (Blueprint $table) {
                $table->dropForeign('responsivas_empresa_tenant_id_foreign');
            });
        } catch (\Throwable $e) {}

        Schema::table('responsivas', function (Blueprint $table) {
            if (Schema::hasColumn('responsivas', 'empresa_tenant_id')) {
                $table->dropColumn('empresa_tenant_id');
            }
            // no elimino folio porque ya lo usas en la UI
        });
    }
};
