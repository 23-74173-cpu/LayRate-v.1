<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks a forecast-generation request across its async lifecycle.
     * ForecastController::generate() used to run the Python subprocess
     * synchronously (up to 300s) inside the HTTP request; it now creates one
     * of these rows, dispatches GenerateForecastJob, and returns
     * immediately. The frontend polls GET /forecast/status/{id} against
     * this row until status is completed/failed.
     */
    public function up(): void
    {
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('scope', ['cage', 'breed', 'farm']);
            $table->foreignId('cage_id')->nullable()->constrained()->nullOnDelete();
            // Kept alongside cage_id so status/history stays readable even if
            // the cage is later deleted (cage_id would go null via nullOnDelete).
            $table->string('cage_code', 50)->nullable();
            $table->string('breed', 100)->nullable();
            $table->unsignedTinyInteger('horizon');
            $table->date('start_date')->nullable();
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->text('error_message')->nullable();
            $table->json('result_metrics')->nullable();
            // Exact params respondAfterGenerate() used to redirect to —
            // stored so the status endpoint can hand the frontend a ready-
            // to-visit URL without reconstructing scope-specific query logic.
            $table->json('redirect_params')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
