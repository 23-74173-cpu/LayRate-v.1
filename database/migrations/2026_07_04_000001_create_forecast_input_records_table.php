<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecast_input_records', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('cage_code', 50);
            $table->string('breed', 100)->nullable();
            $table->unsignedTinyInteger('flock_age_weeks')->nullable();
            $table->unsignedSmallInteger('hen_count')->nullable();
            $table->unsignedSmallInteger('egg_count')->nullable();
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->decimal('humidity_percent', 5, 2)->nullable();
            $table->string('feed_batch_code', 50)->nullable();
            $table->decimal('crude_protein_percent', 5, 2)->nullable();
            $table->decimal('feed_consumed_kg', 8, 2)->nullable();
            $table->unsignedSmallInteger('mortality_count')->nullable()->default(0);
            $table->string('source_file', 255)->nullable();
            $table->timestamps();

            $table->unique(['date', 'cage_code'], 'forecast_input_records_date_cage_unique');
            $table->index(['cage_code', 'date'], 'forecast_input_records_cage_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_input_records');
    }
};
