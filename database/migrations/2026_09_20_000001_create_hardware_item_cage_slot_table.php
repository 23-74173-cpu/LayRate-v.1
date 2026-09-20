<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Additional slots covered by a single sensor (IR breakbeam). The
        // primary slot stays on hardware_items.cage_slot_id — ingestion,
        // occupancy readings, and all single-slot readers keep working
        // unchanged; this pivot only records extra coverage.
        Schema::create('hardware_item_cage_slot', function (Blueprint $table) {
            $table->foreignId('hardware_item_id')->constrained('hardware_items')->cascadeOnDelete();
            $table->foreignId('cage_slot_id')->constrained('cage_slots')->cascadeOnDelete();
            $table->unique(['hardware_item_id', 'cage_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_item_cage_slot');
    }
};
