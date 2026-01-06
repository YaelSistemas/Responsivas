<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('producto_series', function (Blueprint $table) {
            $table->unsignedBigInteger('subsidiaria_id')->nullable()->after('producto_id');

            // index para consultas
            $table->index(['empresa_tenant_id', 'subsidiaria_id'], 'ps_tenant_subs_idx');

            // FK (si tu motor/tablas lo soportan)
            $table->foreign('subsidiaria_id')
                ->references('id')
                ->on('subsidiarias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('producto_series', function (Blueprint $table) {
            $table->dropForeign(['subsidiaria_id']);
            $table->dropIndex('ps_tenant_subs_idx');
            $table->dropColumn('subsidiaria_id');
        });
    }
};
