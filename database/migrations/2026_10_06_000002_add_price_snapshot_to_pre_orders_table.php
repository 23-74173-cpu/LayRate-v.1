<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price snapshot on pre-orders: the unit prices, tray size, and total in
     * effect when the order was priced. Later price edits never rewrite old
     * orders. All nullable — pre-price-era rows keep NULL and display a dash.
     * No backfill, no data changes.
     */
    public function up(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->decimal('unit_price_tray', 10, 2)->nullable()->after('egg_count');
            $table->decimal('unit_price_piece', 10, 2)->nullable()->after('unit_price_tray');
            $table->decimal('total_amount', 10, 2)->nullable()->after('unit_price_piece');
            $table->unsignedSmallInteger('tray_size')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropColumn(['unit_price_tray', 'unit_price_piece', 'total_amount', 'tray_size']);
        });
    }
};
