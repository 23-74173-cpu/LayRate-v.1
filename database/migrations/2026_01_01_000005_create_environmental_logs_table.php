<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('environmental_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->dateTime('recorded_at');
            $table->decimal('temperature_c', 5, 2);
            $table->decimal('humidity_pct', 5, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environmental_logs');
    }
};
