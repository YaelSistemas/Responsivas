<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('producto_series', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('empresa_tenant_id')->index();

            $t->foreignId('producto_id')->constrained('productos')->cascadeOnUpdate()->cascadeOnDelete();

            $t->string('serie');
            $t->enum('estado', ['disponible','asignado','baja','reparacion'])->default('disponible');
            $t->string('ubicacion')->nullable();
            $t->string('observaciones')->nullable();

            $t->timestamps();

            $t->unique(['empresa_tenant_id','serie'], 'ux_tenant_serie');
            $t->index(['empresa_tenant_id','producto_id'], 'ix_tenant_producto');
        });
    }

    public function down(): void {
        Schema::dropIfExists('producto_series');
    }
};
