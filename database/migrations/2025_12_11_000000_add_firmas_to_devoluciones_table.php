<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            if (!Schema::hasColumn('devoluciones', 'firma_entrego_path')) {
                $table->string('firma_entrego_path')->nullable()->after('entrego_colaborador_id');
            }
            if (!Schema::hasColumn('devoluciones', 'firma_psitio_path')) {
                $table->string('firma_psitio_path')->nullable()->after('psitio_colaborador_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropColumn(['firma_entrego_path', 'firma_psitio_path']);
        });
    }
};
