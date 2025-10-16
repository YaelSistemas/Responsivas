<?php

// database/migrations/2025_10_15_000100_add_notas_to_ordenes_compra.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->text('notas')->nullable()->after('descripcion');
        });
    }
    public function down(): void {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn('notas');
        });
    }
};
