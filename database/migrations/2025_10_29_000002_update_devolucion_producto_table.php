<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devolucion_producto', function (Blueprint $table) {
            // 🔹 Agregar campo producto_serie_id si no existe
            if (!Schema::hasColumn('devolucion_producto', 'producto_serie_id')) {
                $table->foreignId('producto_serie_id')
                      ->nullable()
                      ->after('producto_id')
                      ->constrained('producto_series')
                      ->nullOnDelete();
            }

            // 🔹 Eliminar campo cantidad si existe
            if (Schema::hasColumn('devolucion_producto', 'cantidad')) {
                $table->dropColumn('cantidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devolucion_producto', function (Blueprint $table) {
            // 🔹 Restaurar campo cantidad en caso de rollback
            if (!Schema::hasColumn('devolucion_producto', 'cantidad')) {
                $table->integer('cantidad')->default(1);
            }

            // 🔹 Eliminar la columna producto_serie_id
            if (Schema::hasColumn('devolucion_producto', 'producto_serie_id')) {
                $table->dropConstrainedForeignId('producto_serie_id');
            }
        });
    }
};
