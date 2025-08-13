<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_servicio', function (Blueprint $table) {
            // FK diferida para evitar el “huevo y gallina”
            $table->foreign('responsable_id')
                  ->references('id')->on('colaboradores')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('unidades_servicio', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
    }
};
