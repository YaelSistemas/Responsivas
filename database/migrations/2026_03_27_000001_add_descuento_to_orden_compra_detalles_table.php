<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orden_compra_detalles', 'descuento')) {
            Schema::table('orden_compra_detalles', function (Blueprint $table) {
                $table->decimal('descuento', 14, 4)->default(0)->after('precio');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orden_compra_detalles', 'descuento')) {
            Schema::table('orden_compra_detalles', function (Blueprint $table) {
                $table->dropColumn('descuento');
            });
        }
    }
};