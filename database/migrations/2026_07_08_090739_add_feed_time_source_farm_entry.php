<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Whole-farm feeding entries before proportional distribution.
        Schema::create('farm_feed_entries', function (Blueprint $table) {
            $table->id();
            $table->date('log_date');
            $table->time('log_time')->nullable();
            $table->decimal('total_kg', 8, 2);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->foreignId('feed_batch_id')->constrained('feed_batches')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('feed_consumption_logs', function (Blueprint $table) {
            // Add a plain cage_id index first so the FK keeps a backing index
            // when we later drop the unique composite index (MariaDB safety).
            $table->index('cage_id');

            $table->time('log_time')->nullable()->after('log_date');
            $table->enum('source', ['direct', 'distributed'])->default('direct')->after('feed_consumed_kg');
            $table->foreignId('farm_feed_entry_id')->nullable()->after('source')->constrained('farm_feed_entries')->cascadeOnDelete();

            // Multiple entries per cage per day are now allowed.
            $table->dropUnique(['cage_id', 'log_date']);
            $table->index(['cage_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feed_consumption_logs', function (Blueprint $table) {
            $table->dropForeign(['farm_feed_entry_id']);
            $table->dropColumn(['log_time', 'source', 'farm_feed_entry_id']);

            // Restore unique constraint before removing the plain cage_id index.
            $table->unique(['cage_id', 'log_date']);
            $table->dropIndex(['cage_id', 'log_date']);
            $table->dropIndex(['cage_id']);
        });

        Schema::dropIfExists('farm_feed_entries');
    }
};
