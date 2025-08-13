<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colaboradores', function (Blueprint $table) {
            $table->id();

            // Tenant (empresa del sistema)
            $table->unsignedBigInteger('empresa_tenant_id')->index();

            // Folio consecutivo por tenant
            $table->unsignedBigInteger('folio');
            $table->unique(['empresa_tenant_id', 'folio'], 'ux_tenant_folio');

            // Auditoría
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Datos personales
            $table->string('nombre');
            $table->string('apellidos');

            // Relaciones a catálogos
            $table->foreignId('subsidiaria_id')
                  ->constrained('subsidiarias')   // 👈 usa tu tabla 'subsidiarias'
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->foreignId('unidad_servicio_id')
                  ->nullable()
                  ->constrained('unidades_servicio')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();

            $table->foreignId('area_id')
                  ->nullable()
                  ->constrained('areas')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();

            $table->foreignId('puesto_id')
                  ->nullable()
                  ->constrained('puestos')
                  ->cascadeOnUpdate()
                  ->nullOnDelete();

            $table->timestamps();

            // Índices útiles opcionales
            $table->index(['subsidiaria_id', 'unidad_servicio_id']);
            $table->index(['area_id', 'puesto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaboradores');
    }
};
