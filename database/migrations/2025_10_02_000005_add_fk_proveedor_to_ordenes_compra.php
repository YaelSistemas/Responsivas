<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            // Asegura tipo correcto y nullable si usarás SET NULL al borrar:
            $table->unsignedBigInteger('proveedor_id')->nullable()->change();

            // Index (si no existe) + Foreign Key
            $table->index('proveedor_id', 'idx_oc_proveedor_id');
            $table->foreign('proveedor_id', 'fk_oc_proveedor')
                  ->references('id')->on('proveedores')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();   // si borras el proveedor, pone NULL en la OC
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropForeign('fk_oc_proveedor');
            $table->dropIndex('idx_oc_proveedor_id');
            // Si quieres volver a NOT NULL, hazlo aquí manualmente.
        });
    }
};
