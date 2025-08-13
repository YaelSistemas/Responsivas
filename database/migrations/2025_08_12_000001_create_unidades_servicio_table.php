<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_servicio', function (Blueprint $table) {
            $table->id();

            // Tenant (empresa del sistema)
            $table->unsignedBigInteger('empresa_tenant_id')->index();

            // Folio consecutivo por tenant
            $table->unsignedBigInteger('folio');
            $table->unique(['empresa_tenant_id', 'folio'], 'ux_unidad_tenant_folio');

            // Auditoría
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Datos
            $table->string('nombre');
            $table->string('direccion')->nullable();       // ← reemplaza 'descripcion'
            $table->unsignedBigInteger('responsable_id')    // ← aún sin FK, la añadimos después
                  ->nullable()
                  ->index();

            // Unicidad por tenant
            $table->unique(['empresa_tenant_id', 'nombre'], 'ux_unidad_tenant_nombre');

            $table->timestamps();

            // Índices útiles
            $table->index(['empresa_tenant_id', 'nombre'], 'ix_unidad_tenant_nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_servicio');
    }
};
