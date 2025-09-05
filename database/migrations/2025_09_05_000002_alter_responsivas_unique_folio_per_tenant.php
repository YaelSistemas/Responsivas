<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {
            // 1) Quitar el unique global de folio
            //    Usa el nombre EXACTO que te mostró el error: 'responsivas_folio_unique'
            $table->dropUnique('responsivas_folio_unique');

            // 2) Crear el unique compuesto por tenant + folio
            $table->unique(['empresa_tenant_id', 'folio'], 'resp_unique_tenant_folio');
        });
    }

    public function down(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {
            // Revertir al estado anterior
            $table->dropUnique('resp_unique_tenant_folio');
            $table->unique('folio', 'responsivas_folio_unique');
        });
    }
};
