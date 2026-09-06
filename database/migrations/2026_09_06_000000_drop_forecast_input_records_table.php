<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * forecast_input_records was a denormalized copy produced by
     * ForecastInputSync. The forecasting pipeline now aggregates the dataset
     * on demand from the native production tables, so the copy is gone.
     */
    public function up(): void
    {
        Schema::dropIfExists('forecast_input_records');
    }

    public function down(): void
    {
        Schema::create('forecast_input_records', function ($table) {
            $table->id();
            $table->date('date');
            $table->string('cage_code', 50);
            $table->string('breed', 100)->nullable();
            $table->unsignedInteger('flock_age_weeks')->nullable();
            $table->unsignedInteger('hen_count')->default(0);
            $table->unsignedInteger('egg_count')->default(0);
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->decimal('humidity_percent', 5, 2)->nullable();
            $table->decimal('crude_protein_percent', 5, 2)->nullable();
            $table->decimal('feed_consumed_kg', 6, 2)->nullable();
            $table->unsignedInteger('mortality_count')->default(0);
            $table->string('source_file', 255)->nullable();
            $table->timestamps();
            $table->unique(['date', 'cage_code']);
        });
    }
};