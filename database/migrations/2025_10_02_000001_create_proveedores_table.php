<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_tenant_id')->index(); // <- tenant
            $table->string('nombre');
            $table->string('calle')->nullable();
            $table->string('colonia')->nullable();
            $table->string('codigo_postal', 15)->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->timestamps();

            // Si tienes tabla empresas y quieres FK, descomenta:
            // $table->foreign('empresa_tenant_id')->references('id')->on('empresas')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('proveedores');
    }
};