<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('oc_counters', function (Blueprint $t) {
            $t->id();
            // Si manejas multi-empresa/tenant:
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('last_seq')->default(0);
            $t->timestamps();

            // Un renglón por tenant (si no usas tenant, cambia por unique('id') implícito)
            $t->unique(['tenant_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('oc_counters');
    }
};
