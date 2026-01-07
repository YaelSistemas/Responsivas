<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('producto_series', function (Blueprint $table) {
            // Si ya existe, evita error
            if (!Schema::hasColumn('producto_series', 'unidad_servicio_id')) {

                $table->unsignedBigInteger('unidad_servicio_id')
                    ->nullable()
                    ->after('subsidiaria_id');

                $table->foreign('unidad_servicio_id')
                    ->references('id')
                    ->on('unidades_servicio')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('producto_series', function (Blueprint $table) {
            if (Schema::hasColumn('producto_series', 'unidad_servicio_id')) {
                $table->dropForeign(['unidad_servicio_id']);
                $table->dropColumn('unidad_servicio_id');
            }
        });
    }
};
