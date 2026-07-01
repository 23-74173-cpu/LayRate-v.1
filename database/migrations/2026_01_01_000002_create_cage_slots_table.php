<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cage_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('row_number');
            $table->unsignedTinyInteger('column_number');
            $table->unsignedTinyInteger('slot_number');
            $table->unsignedTinyInteger('current_occupancy')->default(0);
            $table->boolean('has_sensor')->default(false);
            $table->string('sensor_device_id', 100)->nullable();
            $table->timestamps();
            $table->unique(['cage_id', 'slot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cage_slots');
    }
};
