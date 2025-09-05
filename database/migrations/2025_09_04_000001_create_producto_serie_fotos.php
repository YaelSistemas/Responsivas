<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('producto_serie_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_serie_id')->constrained('producto_series')->cascadeOnDelete();
            $table->string('path');               // ruta en storage
            $table->string('caption')->nullable();// nota opcional
            $table->boolean('defecto')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('producto_serie_fotos');
    }
};