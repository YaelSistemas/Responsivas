<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('producto_existencias', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('empresa_tenant_id')->index();

            $t->foreignId('producto_id')->constrained('productos')->cascadeOnUpdate()->cascadeOnDelete();

            $t->unsignedInteger('cantidad')->default(0);

            $t->timestamps();

            $t->unique(['empresa_tenant_id','producto_id'], 'ux_tenant_producto_stock');
        });
    }

    public function down(): void {
        Schema::dropIfExists('producto_existencias');
    }
};
