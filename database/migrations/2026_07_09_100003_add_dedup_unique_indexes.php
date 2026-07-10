<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotency: a Pi retrying the same reading must not create duplicates.
        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->unique(['cage_id', 'recorded_at'], 'env_logs_cage_recorded_unique');
        });

        Schema::table('sensor_occupancy_readings', function (Blueprint $table) {
            $table->unique(['hardware_item_id', 'recorded_at'], 'sensor_occ_hw_recorded_unique');
        });
    }

    public function down(): void
    {
        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->dropUnique('env_logs_cage_recorded_unique');
        });

        Schema::table('sensor_occupancy_readings', function (Blueprint $table) {
            $table->dropUnique('sensor_occ_hw_recorded_unique');
        });
    }
};
