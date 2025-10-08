<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ordenes_compra')) {
            return; // En entornos limpios evita romper
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            if (!Schema::hasColumn('ordenes_compra', 'proveedor_id')) {
                $table->unsignedBigInteger('proveedor_id')
                      ->nullable()
                      ->after('solicitante_id')
                      ->index();

                // Si quieres FK (descomenta cuando la tabla proveedores ya exista)
                // $table->foreign('proveedor_id')
                //       ->references('id')->on('proveedores')
                //       ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ordenes_compra')) {
            return;
        }

        Schema::table('ordenes_compra', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_compra', 'proveedor_id')) {
                // $table->dropForeign(['proveedor_id']); // si creaste la FK
                $table->dropColumn('proveedor_id');
            }
        });
    }
};
