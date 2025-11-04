<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_producto', function (Blueprint $table) {
            $table->id();

            // Relaciones base
            $table->foreignId('devolucion_id')
                  ->constrained('devoluciones')
                  ->cascadeOnDelete();

            $table->foreignId('producto_id')
                  ->constrained('productos')
                  ->cascadeOnDelete();

            // Cantidad solo si era parte del diseño original (se eliminará en update)
            $table->integer('cantidad')->default(1);

            // Timestamps para la relación (por el ->withTimestamps() en tu modelo)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_producto');
    }
};
