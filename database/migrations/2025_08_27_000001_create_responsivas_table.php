<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('responsivas', function (Blueprint $t) {
            $t->id();
            $t->string('folio')->unique();
            $t->unsignedBigInteger('colaborador_id');          // a quién se asigna
            $t->unsignedBigInteger('user_id');                 // quién crea / entrega
            $t->date('fecha_entrega')->nullable();
            $t->text('observaciones')->nullable();
            $t->timestamps();

            $t->foreign('colaborador_id')->references('id')->on('colaboradores')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('responsivas'); }
};
