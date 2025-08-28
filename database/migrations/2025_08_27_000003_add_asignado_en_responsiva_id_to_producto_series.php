<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('producto_series', function (Blueprint $t) {
            if (!Schema::hasColumn('producto_series','estado')) {
                $t->string('estado')->default('disponible')->index();
            }
            if (!Schema::hasColumn('producto_series','asignado_en_responsiva_id')) {
                $t->unsignedBigInteger('asignado_en_responsiva_id')->nullable()->after('estado')->index();
                if (Schema::hasTable('responsivas')) {
                    $t->foreign('asignado_en_responsiva_id')->references('id')->on('responsivas')->nullOnDelete();
                }
            }
        });

        // Normaliza nulos (por si ya hay filas)
        DB::table('producto_series')->whereNull('estado')->update(['estado' => 'disponible']);
    }

    public function down(): void
    {
        Schema::table('producto_series', function (Blueprint $t) {
            if (Schema::hasColumn('producto_series','asignado_en_responsiva_id')) {
                try { $t->dropForeign(['asignado_en_responsiva_id']); } catch (\Throwable $e) {}
                $t->dropColumn('asignado_en_responsiva_id');
            }
            // Quitar 'estado' solo si quieres revertir completamente:
            // if (Schema::hasColumn('producto_series','estado')) $t->dropColumn('estado');
        });
    }
};
