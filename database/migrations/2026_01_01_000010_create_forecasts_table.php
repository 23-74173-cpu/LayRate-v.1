<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cage_slot_id')->nullable()->constrained('cage_slots')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->date('target_date');
            $table->decimal('predicted_hdep', 5, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};
