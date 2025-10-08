<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            // multi-tenant: empresa activa (igual que usas en sesión)
            $table->unsignedBigInteger('empresa_tenant_id')->index();

            // datos principales
            $table->string('numero_orden');                // No. de orden
            $table->date('fecha')->nullable();             // Fecha
            $table->unsignedBigInteger('solicitante_id');  // Colaborador solicitante
            $table->string('proveedor');                   // Proveedor
            $table->text('descripcion')->nullable();       // Descripción
            $table->decimal('monto', 12, 2)->default(0);   // Monto
            $table->string('factura')->nullable();         // Folio de factura (o nombre/URL si luego adjuntas archivo)

            $table->timestamps();

            // Relaciones (asumiendo tablas existentes)
            $table->foreign('empresa_tenant_id')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('solicitante_id')->references('id')->on('colaboradores')->restrictOnDelete();

            // Evita duplicados de número por empresa
            $table->unique(['empresa_tenant_id', 'numero_orden'], 'oc_tenant_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
