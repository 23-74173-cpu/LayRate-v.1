<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_count_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hardware_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('event_type', ['sensor_trigger', 'manual_override']);
            $table->unsignedInteger('old_value')->nullable();
            $table->unsignedInteger('new_value');
            $table->unsignedInteger('trigger_count')->nullable();
            $table->string('source', 100);
            $table->timestamp('occurred_at');
            $table->string('event_key', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['cage_slot_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_count_events');
    }
};
