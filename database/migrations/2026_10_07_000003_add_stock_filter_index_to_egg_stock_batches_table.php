<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index for the stock batches table filters (harvested range, size, and
     * freshness — freshness resolves to harvested_date cutoffs, so it rides
     * the same index). egg_stock_batches has no index on any filter column
     * today; at ~2k rows and growing daily the filtered table would
     * otherwise full-scan on every keystroke (debounced) and every page.
     *
     * Additive and reversible: creates one composite index, changes no data.
     * Small table — builds in well under a second on the Pi.
     */
    public function up(): void
    {
        Schema::table('egg_stock_batches', function (Blueprint $table) {
            $table->index(['harvested_date', 'egg_size'], 'stock_batches_harvested_size_index');
        });
    }

    public function down(): void
    {
        Schema::table('egg_stock_batches', function (Blueprint $table) {
            $table->dropIndex('stock_batches_harvested_size_index');
        });
    }
};
