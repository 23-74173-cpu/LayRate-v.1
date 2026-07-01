<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mortality_log_hens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mortality_log_id')->constrained('mortality_logs')->cascadeOnDelete();
            $table->foreignId('hen_id')->constrained('hens')->cascadeOnDelete();
            $table->foreignId('cage_slot_id')->constrained('cage_slots')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mortality_log_id', 'hen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortality_log_hens');
    }
};
