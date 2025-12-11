<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devolucion_firma_links', function (Blueprint $table) {
            $table->string('campo')
                  ->default('entrego')   // los registros viejos se consideran de ENTREGÓ
                  ->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('devolucion_firma_links', function (Blueprint $table) {
            $table->dropColumn('campo');
        });
    }
};

