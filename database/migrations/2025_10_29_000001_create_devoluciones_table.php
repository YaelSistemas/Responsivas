<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_tenant_id')->index();
            $table->string('folio', 20)->unique();

            $table->foreignId('responsiva_id')->constrained('responsivas')->onDelete('cascade');
            $table->date('fecha_devolucion');
            $table->enum('motivo', ['baja_colaborador','renovacion']);
            $table->foreignId('recibi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entrego_colaborador_id')->nullable()->constrained('colaboradores')->nullOnDelete();
            $table->foreignId('psitio_colaborador_id')->nullable()->constrained('colaboradores')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};

