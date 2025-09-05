<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // suficiente para 'impresora_multifuncional' etc.
            $table->string('tipo', 30)->change();
        });
    }

    public function down(): void
    {
        // Si antes era ENUM, puedes volver a ENUM (opcional)
        // O dejarlo como string. Si quieres revertir a ENUM:
        // use Illuminate\Support\Facades\DB;
        // DB::statement("ALTER TABLE productos
        //   MODIFY COLUMN tipo ENUM('equipo_pc','impresora','periferico','consumible','otro') NOT NULL");
    }
};

