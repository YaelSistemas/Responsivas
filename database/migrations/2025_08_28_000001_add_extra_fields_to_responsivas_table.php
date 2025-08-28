<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {
            // Motivo de entrega
            $table->enum('motivo_entrega', ['asignacion','prestamo_provisional'])
                  ->default('asignacion')
                  ->after('observaciones');

            // Recibí (puede ser distinto al colaborador asignado)
            $table->unsignedBigInteger('recibi_colaborador_id')->nullable()->after('colaborador_id');

            // Autorizó
            $table->unsignedBigInteger('autoriza_user_id')->nullable()->after('user_id');

            // FKs (ajústalas si tus nombres de tablas/campos difieren)
            $table->foreign('recibi_colaborador_id')->references('id')->on('colaboradores')->nullOnDelete();
            $table->foreign('autoriza_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('responsivas', function (Blueprint $table) {
            $table->dropForeign(['recibi_colaborador_id']);
            $table->dropForeign(['autoriza_user_id']);
            $table->dropColumn(['motivo_entrega','recibi_colaborador_id','autoriza_user_id']);
        });
    }
};
