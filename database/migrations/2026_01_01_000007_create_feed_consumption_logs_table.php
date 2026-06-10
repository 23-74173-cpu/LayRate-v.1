<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feed_consumption_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_batch_id')->constrained('feed_batches');
            $table->date('log_date');
            $table->decimal('feed_consumed_kg', 6, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cage_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_consumption_logs');
    }
};
