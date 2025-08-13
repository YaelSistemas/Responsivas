<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('puestos', function (Blueprint $table) {
            $table->id();

            // Tenant (empresa del sistema) y folio por tenant
            $table->unsignedBigInteger('empresa_tenant_id')->index();
            $table->unsignedBigInteger('folio');
            $table->unique(['empresa_tenant_id', 'folio'], 'ux_puestos_tenant_folio');

            // Auditoría
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Datos
            $table->string('nombre');
            $table->string('descripcion')->nullable();

            $table->timestamps();

            // Unicidad por tenant (mismo nombre puede existir en otras empresas)
            $table->unique(['empresa_tenant_id', 'nombre'], 'ux_puestos_tenant_nombre');

            // Índices útiles
            $table->index(['empresa_tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puestos');
    }
};
