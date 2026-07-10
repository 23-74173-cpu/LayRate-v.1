<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_occupancy_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hardware_item_id')->constrained('hardware_items')->cascadeOnDelete();
            $table->foreignId('cage_slot_id')->constrained('cage_slots')->cascadeOnDelete();
            $table->unsignedInteger('reported_count');
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_occupancy_readings');
    }
};
