<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            // RFC mexicano normalmente 12 ó 13 caracteres
            $table->string('rfc', 13)->nullable()->after('nombre');

            // Índice compuesto por tenant + rfc (no unique por ser nullable)
            $table->index(['empresa_tenant_id', 'rfc'], 'proveedores_tenant_rfc_idx');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex('proveedores_tenant_rfc_idx');
            $table->dropColumn('rfc');
        });
    }
};
