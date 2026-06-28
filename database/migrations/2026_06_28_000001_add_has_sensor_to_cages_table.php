<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cages', function (Blueprint $table) {
            $table->boolean('has_sensor')->default(false)->after('is_active');
            $table->string('sensor_device_id', 100)->nullable()->after('has_sensor');
        });
    }

    public function down(): void
    {
        Schema::table('cages', function (Blueprint $table) {
            $table->dropColumn(['has_sensor', 'sensor_device_id']);
        });
    }
};
