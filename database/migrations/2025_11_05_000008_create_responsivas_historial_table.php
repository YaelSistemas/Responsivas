<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('responsivas_historial', function (Blueprint $table) {
            $table->id();

            // ID de la responsiva
            $table->unsignedBigInteger('responsiva_id');

            // Usuario que hizo el cambio
            $table->unsignedBigInteger('user_id')->nullable();

            // Acción: Creación, Actualización, Eliminación
            $table->string('accion', 50);

            // Cambios en formato JSON
            $table->json('cambios')->nullable();

            $table->timestamps();

            // Clave foránea
            $table->foreign('responsiva_id')->references('id')->on('responsivas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('responsivas_historial');
    }
};
