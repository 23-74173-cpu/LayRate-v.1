<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hardware_items', function (Blueprint $table) {
            $table->id();
            $table->enum('device_type', ['DHT22', 'IR_breakbeam', 'relay', 'other']);
            $table->string('serial_number', 100)->unique();
            $table->foreignId('cage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cage_slot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('installation_date')->nullable();
            $table->enum('status', ['active', 'faulty', 'removed', 'spare'])->default('active');
            $table->date('last_calibration_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_items');
    }
};
