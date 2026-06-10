<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255)->nullable();
            $table->string('label', 150)->nullable();
            $table->timestamps();
        });

        // Insert default threshold values
        DB::table('settings')->insert([
            ['key' => 'temp_min',  'value' => '18', 'label' => 'Temperature Minimum (°C)', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'temp_max',  'value' => '30', 'label' => 'Temperature Maximum (°C)', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'hum_min',   'value' => '40', 'label' => 'Humidity Minimum (%)',      'created_at' => now(), 'updated_at' => now()],
            ['key' => 'hum_max',   'value' => '70', 'label' => 'Humidity Maximum (%)',      'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
