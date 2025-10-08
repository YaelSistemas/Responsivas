<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orden_compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_compra_id');

            $table->decimal('cantidad', 12, 3)->default(1);
            $table->string('unidad', 50)->nullable();     // p. ej. pza, caja, m, kg
            $table->string('concepto', 500);              // descripción de la partida

            $table->string('moneda', 10)->default('MXN'); // MXN, USD...
            $table->decimal('precio', 14, 4)->default(0); // precio unitario
            $table->decimal('importe', 14, 4)->default(0);// cantidad*precio (guardamos por seguridad)

            $table->decimal('iva_pct', 5, 2)->default(16); // % IVA aplicado a esta partida
            $table->decimal('iva_monto', 14, 4)->default(0);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);

            $table->timestamps();

            // FK (si ya tienes FK hacia proveedores/colaboradores haz lo mismo)
            $table->foreign('orden_compra_id')
                  ->references('id')->on('ordenes_compra')
                  ->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('orden_compra_detalles');
    }
};
