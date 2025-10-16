<?php

// database/migrations/2025_10_16_000000_add_estado_to_ordenes_compra_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('estado', 20)->default('abierta')->index()->after('factura');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
