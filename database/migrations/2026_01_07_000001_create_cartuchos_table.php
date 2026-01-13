<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cartuchos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('empresa_tenant_id')->index();
            $table->string('folio', 20)->unique();

            $table->date('fecha_solicitud');

            $table->foreignId('colaborador_id')
                ->constrained('colaboradores')
                ->onDelete('restrict');

            // "equipo" = producto seleccionable
            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('productos')
                ->nullOnDelete();

            // "realizado_por" = user del sistema
            $table->foreignId('realizado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // firmas (ruta / base64 / lo que uses)
            $table->text('firma_realizo')->nullable();
            $table->text('firma_recibio')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartuchos');
    }
};
