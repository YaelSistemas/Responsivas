<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            // eliminar UNIQUE global
            $table->dropUnique('devoluciones_folio_unique');

            // crear UNIQUE por empresa + folio
            $table->unique(['empresa_tenant_id', 'folio'], 'devoluciones_empresa_folio_unique');
        });
    }

    public function down()
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropUnique('devoluciones_empresa_folio_unique');
            $table->unique('folio', 'devoluciones_folio_unique');
        });
    }
};
