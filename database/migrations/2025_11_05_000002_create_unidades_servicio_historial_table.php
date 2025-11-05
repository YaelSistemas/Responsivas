<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unidades_servicio_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidad_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('accion')->default('Actualización');
            $table->json('cambios')->nullable();
            $table->timestamps();

            $table->foreign('unidad_id')->references('id')->on('unidades_servicio')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_servicio_historial');
    }
};
