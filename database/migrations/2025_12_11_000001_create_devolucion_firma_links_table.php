<?php

// database/migrations/2025_12_11_000000_create_devolucion_firma_links_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devolucion_firma_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')
                ->constrained('devoluciones')
                ->cascadeOnDelete();
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable(); // opcional (p.ej. 7 días)
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_firma_links');
    }
};