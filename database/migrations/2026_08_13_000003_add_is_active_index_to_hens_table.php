<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * hens.is_active is filtered in 50+ places (Dashboard, Analytics,
     * Reports, FcrCalculator, every "hens" eager-load constraint on Cage
     * and CageSlot). Cage::hens() is a HasManyThrough via cage_slots, so
     * the through-join already uses the FK-implicit indexes on
     * cage_slots.cage_id / hens.cage_slot_id — is_active is the one
     * predicate in that join with no index at all. Also serves the
     * fully-unscoped `Hen::where('is_active', 1)->count()` in
     * DashboardController used for the farm-wide total-hens KPI.
     *
     * Impact note: unlike production_logs/mortality_logs/alerts, the hens
     * table does not grow without bound (row count tracks flock size, not
     * elapsed operating time), so this is a smaller, flatter win than the
     * others in this pass rather than one that compounds over months of
     * uptime — still worth doing since it's read on every dashboard load,
     * just don't expect it to be the biggest line item.
     */
    public function up(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->index('is_active', 'hens_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->dropIndex('hens_is_active_index');
        });
    }
};
