<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cage can hold up to 10 × 100 slots, and renumbering parks slots on
     * temporary numbers above max + count, so TINYINT (≤ 255) overflowed.
     */
    public function up(): void
    {
        Schema::table('cage_slots', function (Blueprint $table) {
            $table->unsignedSmallInteger('slot_number')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cage_slots', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot_number')->change();
        });
    }
};
