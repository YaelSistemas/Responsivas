<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('productos', function (Blueprint $t) {
            $t->id();

            // Multi-tenant + folio + auditoría
            $t->unsignedBigInteger('empresa_tenant_id')->index();
            $t->unsignedBigInteger('folio');
            $t->unique(['empresa_tenant_id', 'folio'], 'ux_producto_tenant_folio');
            $t->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();

            // Datos base
            $t->string('nombre');
            $t->string('sku')->nullable(); // único por tenant si se usa
            $t->string('marca')->nullable();
            $t->string('modelo')->nullable();
            $t->enum('tipo', ['equipo','periferico','consumible'])->default('equipo');
            $t->boolean('es_serializado')->default(false);
            $t->string('unidad')->default('pieza');
            $t->json('especificaciones')->nullable();
            $t->boolean('activo')->default(true);

            $t->timestamps();

            $t->unique(['empresa_tenant_id','sku'], 'ux_producto_tenant_sku');
            $t->index(['empresa_tenant_id','nombre'], 'ix_producto_tenant_nombre');
        });
    }

    public function down(): void {
        Schema::dropIfExists('productos');
    }
};
