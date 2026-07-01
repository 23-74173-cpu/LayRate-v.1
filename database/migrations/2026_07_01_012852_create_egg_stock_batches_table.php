<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('egg_stock_batches', function (Blueprint $table) {
            $table->id();
            $table->string('egg_size', 10);
            $table->unsignedInteger('count');
            $table->date('harvested_date');
            $table->foreignId('cage_id')->nullable()->constrained('cages')->nullOnDelete();
            $table->foreignId('cage_slot_id')->nullable()->constrained('cage_slots')->nullOnDelete();
            $table->foreignId('source_production_log_id')->nullable()->constrained('production_logs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_stock_batches');
    }
};
