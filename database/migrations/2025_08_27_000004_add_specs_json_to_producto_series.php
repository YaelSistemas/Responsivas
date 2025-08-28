<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('producto_series', function (Blueprint $t) {
      if (!Schema::hasColumn('producto_series','especificaciones')) {
        $t->json('especificaciones')->nullable()->after('observaciones');
      }
      // (Opcional) columnas generadas para búsquedas por serie
      if (!Schema::hasColumn('producto_series','ram_gb_index')) {
        $t->unsignedSmallInteger('ram_gb_index')
          ->nullable()
          ->storedAs("JSON_EXTRACT(especificaciones, '$.ram_gb')");
        $t->index('ram_gb_index');
      }
      if (!Schema::hasColumn('producto_series','alm_tipo_index')) {
        $t->string('alm_tipo_index', 10)
          ->nullable()
          ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(especificaciones, '$.almacenamiento.tipo'))");
        $t->index('alm_tipo_index');
      }
    });
  }
  public function down(): void {
    Schema::table('producto_series', function (Blueprint $t) {
      if (Schema::hasColumn('producto_series','alm_tipo_index')) $t->dropColumn('alm_tipo_index');
      if (Schema::hasColumn('producto_series','ram_gb_index'))  $t->dropColumn('ram_gb_index');
      if (Schema::hasColumn('producto_series','especificaciones')) $t->dropColumn('especificaciones');
    });
  }
};
