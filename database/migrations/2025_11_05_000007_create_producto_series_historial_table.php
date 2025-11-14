<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('producto_series_historial', function (Blueprint $t) {
            $t->id();

            // Multi-tenant
            $t->unsignedBigInteger('empresa_tenant_id')->index();

            // Serie a la que pertenece el movimiento
            $t->foreignId('producto_serie_id')
                ->constrained('producto_series')
                ->cascadeOnDelete();

            // Lo guardamos denormalizado para consultas rápidas
            $t->unsignedBigInteger('producto_id')->index();

            // Quién hizo el movimiento
            $t->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Tipo de movimiento
            // ejemplo: asignacion, devolucion, edicion, creacion, baja, reparacion
            $t->string('accion', 30);

            // Cambio de estado
            $t->string('estado_anterior', 20)->nullable();
            $t->string('estado_nuevo', 20)->nullable();

            // En qué documento ocurrió (si aplica)
            $t->unsignedBigInteger('responsiva_id')->nullable()->index();
            $t->unsignedBigInteger('devolucion_id')->nullable()->index();

            // Cambios de datos (color, ram, almacenamiento, etc.) en JSON
            // Ejemplo:
            // { "color": "Negra → Blanca", "ram_gb": "8 → 16" }
            $t->json('cambios')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_series_historial');
    }
};
