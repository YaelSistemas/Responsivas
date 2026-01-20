<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('orden_compra_detalles', function (Blueprint $table) {
      $table->decimal('ret_iva_pct', 8, 3)->default(0)->after('isr_monto');
      $table->decimal('ret_iva_monto', 12, 4)->default(0)->after('ret_iva_pct');
    });
  }

  public function down(): void
  {
    Schema::table('orden_compra_detalles', function (Blueprint $table) {
      $table->dropColumn(['ret_iva_pct','ret_iva_monto']);
    });
  }
};

