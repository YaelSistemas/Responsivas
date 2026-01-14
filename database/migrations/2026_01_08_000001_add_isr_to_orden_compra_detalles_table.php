<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orden_compra_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('orden_compra_detalles', 'isr_pct')) {
                $table->decimal('isr_pct', 6, 3)->default(0)->after('iva_monto');
            }
            if (!Schema::hasColumn('orden_compra_detalles', 'isr_monto')) {
                $table->decimal('isr_monto', 14, 4)->default(0)->after('isr_pct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_compra_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('orden_compra_detalles', 'isr_monto')) $table->dropColumn('isr_monto');
            if (Schema::hasColumn('orden_compra_detalles', 'isr_pct'))   $table->dropColumn('isr_pct');
        });
    }
};
