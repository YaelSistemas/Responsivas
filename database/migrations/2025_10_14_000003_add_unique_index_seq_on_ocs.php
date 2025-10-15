<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_unique_index_seq_on_ocs.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ordenes_compra', function (Blueprint $t) {
            // si ya existe, ponle otro nombre
            $t->unique(['empresa_tenant_id', 'seq'], 'oc_tenant_seq_unique');
        });
    }
    public function down(): void {
        Schema::table('ordenes_compra', function (Blueprint $t) {
            $t->dropUnique('oc_tenant_seq_unique');
        });
    }
};
