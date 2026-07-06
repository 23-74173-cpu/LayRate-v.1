<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Unplaced hens (cage_slot_id = null) form the chicken inventory that
     * Bulk Add draws from, and are the target of the "move hens to unplaced"
     * option when deleting a cage.
     */
    public function up(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->dropForeign(['cage_slot_id']);
        });

        Schema::table('hens', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_slot_id')->nullable()->change();
            $table->foreign('cage_slot_id')->references('id')->on('cage_slots')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->dropForeign(['cage_slot_id']);
        });

        Schema::table('hens', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_slot_id')->nullable(false)->change();
            $table->foreign('cage_slot_id')->references('id')->on('cage_slots')->cascadeOnDelete();
        });
    }
};
