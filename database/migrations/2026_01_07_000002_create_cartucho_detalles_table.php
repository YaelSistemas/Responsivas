<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cartucho_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cartucho_id');
            $table->unsignedBigInteger('producto_id'); // <- el cartucho/toner (consumible)
            $table->unsignedInteger('cantidad')->default(1);
            $table->timestamps();

            $table->foreign('cartucho_id')->references('id')->on('cartuchos')->cascadeOnDelete();
            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();

            $table->index(['cartucho_id']);
            $table->index(['producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartucho_detalles');
    }
};
