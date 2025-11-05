<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('colaboradores_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('accion'); // creación, edición, eliminación, etc.
            $table->json('cambios')->nullable(); // campos modificados
            $table->timestamps();

            $table->foreign('colaborador_id')->references('id')->on('colaboradores')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaboradores_historial');
    }
};
