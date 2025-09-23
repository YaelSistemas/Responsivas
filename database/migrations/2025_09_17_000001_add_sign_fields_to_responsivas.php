<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('responsivas', function (Blueprint $table) {
            $table->string('sign_token', 80)->nullable()->index();
            $table->timestamp('sign_token_expires_at')->nullable();

            $table->string('firma_colaborador_path')->nullable();
            $table->string('firmado_por')->nullable();
            $table->timestamp('firmado_en')->nullable();
            $table->string('firmado_ip', 64)->nullable();
        });
    }

    public function down(): void {
        Schema::table('responsivas', function (Blueprint $table) {
            $table->dropColumn([
                'sign_token','sign_token_expires_at',
                'firma_colaborador_path','firmado_por','firmado_en','firmado_ip'
            ]);
        });
    }
};
