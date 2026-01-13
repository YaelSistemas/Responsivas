<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cartuchos', function (Blueprint $table) {
            $table->string('firma_colaborador_path')->nullable()->after('updated_at');
            $table->timestamp('firma_colaborador_at')->nullable()->after('firma_colaborador_path');

            // para firma por link
            $table->string('firma_token', 80)->nullable()->unique()->after('firma_colaborador_at');
            $table->timestamp('firma_token_expires_at')->nullable()->after('firma_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cartuchos', function (Blueprint $table) {
            //
        });
    }
};
