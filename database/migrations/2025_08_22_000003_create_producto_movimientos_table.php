<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('producto_movimientos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('empresa_tenant_id')->index();
            $t->foreignId('producto_id')->constrained('productos')->cascadeOnUpdate()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            $t->enum('tipo', ['entrada','salida','ajuste']);
            $t->integer('cantidad');          // positiva para entrada, negativa para salida, libre para ajuste
            $t->string('motivo')->nullable(); // opcional (p. ej. “compra”, “corrección”, “consumo”)
            $t->string('referencia')->nullable(); // folio/orden/oc/resp, si aplica

            $t->timestamps();

            $t->index(['empresa_tenant_id','producto_id'],'ix_mov_tenant_producto');
        });
    }
    public function down(): void {
        Schema::dropIfExists('producto_movimientos');
    }
};
