<?php

// database/migrations/2025_10_16_000500_create_oc_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('oc_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40); // created, updated, status_changed, attachment_added, attachment_removed
            $table->json('data')->nullable(); // payload con detalles (campos, de→a, archivo, etc.)
            $table->timestamps();
            $table->index(['orden_compra_id', 'created_at']);
            $table->index(['type']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('oc_logs');
    }
};
