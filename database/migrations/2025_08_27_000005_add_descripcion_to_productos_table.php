<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // agrega descripcion si no existe
            if (!Schema::hasColumn('productos', 'descripcion')) {
                $table->text('descripcion')->nullable()->after('modelo');
            }

            // por si en algún entorno no existiera la columna JSON
            if (!Schema::hasColumn('productos', 'especificaciones')) {
                $table->json('especificaciones')->nullable()->after('unidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'descripcion')) {
                $table->dropColumn('descripcion');
            }
            // si quieres, NO borres 'especificaciones' en down() para no perder datos
            // if (Schema::hasColumn('productos', 'especificaciones')) {
            //     $table->dropColumn('especificaciones');
            // }
        });
    }
};
