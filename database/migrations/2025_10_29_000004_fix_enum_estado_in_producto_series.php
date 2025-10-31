<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Forzar recreación del ENUM con todos los valores válidos
        DB::statement("
            ALTER TABLE producto_series 
            CHANGE estado estado 
            ENUM('disponible','asignado','devuelto','baja','reparacion') 
            NOT NULL DEFAULT 'disponible'
        ");
    }

    public function down(): void
    {
        // Revertir al ENUM original
        DB::statement("
            ALTER TABLE producto_series 
            CHANGE estado estado 
            ENUM('disponible','asignado','baja','reparacion') 
            NOT NULL DEFAULT 'disponible'
        ");
    }
};
