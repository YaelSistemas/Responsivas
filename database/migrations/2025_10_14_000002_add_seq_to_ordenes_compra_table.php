<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ordenes_compra', function (Blueprint $t) {
            // 1) Añadimos seq como nullable para no romper datos actuales
            $t->unsignedBigInteger('seq')->nullable()->after('id');

            // 2) Índice normal por ahora (el unique lo pondremos después del backfill)
            $t->index('seq');

            // Si tienes multi-empresa y ya existe tenant_id, no pongas unique aún.
            // El unique(['tenant_id','seq']) lo agregamos en una Fase posterior,
            // cuando ya hayamos rellenado seq en todos los registros.
        });
    }
    public function down(): void {
        Schema::table('ordenes_compra', function (Blueprint $t) {
            $t->dropIndex(['seq']);
            $t->dropColumn('seq');
        });
    }
};
