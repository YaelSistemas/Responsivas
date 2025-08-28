<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {
            // Fecha de solicitud (opcional)
            if (!Schema::hasColumn('responsivas', 'fecha_solicitud')) {
                $table->date('fecha_solicitud')->nullable()->after('folio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {
            if (Schema::hasColumn('responsivas', 'fecha_solicitud')) {
                $table->dropColumn('fecha_solicitud');
            }
        });
    }
};
