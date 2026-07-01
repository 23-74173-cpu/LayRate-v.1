<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cage_slots', function (Blueprint $table) {
            $table->dropColumn(['has_sensor', 'sensor_device_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cage_slots', function (Blueprint $table) {
            $table->boolean('has_sensor')->default(false);
            $table->string('sensor_device_id', 100)->nullable();
        });
    }
};
