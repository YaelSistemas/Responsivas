<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();

            // 🏢 Tenant (empresa del sistema)
            $table->unsignedBigInteger('empresa_tenant_id')->index();

            // 🔢 Folio consecutivo por tenant
            $table->unsignedBigInteger('folio');
            $table->unique(['empresa_tenant_id', 'folio'], 'ux_areas_tenant_folio');

            // 🧑‍💻 Auditoría
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // 📇 Datos del catálogo
            $table->string('nombre');
            $table->string('descripcion')->nullable();

            $table->timestamps();

            // Evita duplicados por tenant
            $table->unique(['empresa_tenant_id', 'nombre'], 'ux_areas_tenant_nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
