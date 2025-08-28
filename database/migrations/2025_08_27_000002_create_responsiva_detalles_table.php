<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('responsiva_detalles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('responsiva_id');
            $t->unsignedBigInteger('producto_id');
            $t->unsignedBigInteger('producto_serie_id'); // la pieza específica
            $t->timestamps();

            $t->foreign('responsiva_id')->references('id')->on('responsivas')->cascadeOnDelete();
            $t->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
            $t->foreign('producto_serie_id')->references('id')->on('producto_series')->cascadeOnDelete();

            $t->unique(['responsiva_id','producto_serie_id']); // no repetir serie
        });
    }
    public function down(): void { Schema::dropIfExists('responsiva_detalles'); }
};
