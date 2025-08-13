<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subsidiarias', function (Blueprint $table) {
            $table->id();

            // Tenant + folio por tenant
            $table->unsignedBigInteger('empresa_tenant_id')->index();
            $table->unsignedBigInteger('folio');
            $table->unique(['empresa_tenant_id', 'folio'], 'ux_subsidiarias_tenant_folio');

            // Auditoría
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Datos
            $table->string('nombre');
            $table->string('descripcion')->nullable();

            $table->timestamps();

            // Unicidad de nombre dentro del tenant
            $table->unique(['empresa_tenant_id', 'nombre'], 'ux_subsidiarias_tenant_nombre');

            // Índices útiles
            $table->index(['empresa_tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidiarias');
    }
};
