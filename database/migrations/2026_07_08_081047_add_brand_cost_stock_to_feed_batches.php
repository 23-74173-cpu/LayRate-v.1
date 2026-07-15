<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feed_batches', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('batch_code');
            $table->decimal('total_quantity_kg', 10, 2)->nullable()->after('crude_protein');
            $table->decimal('unit_cost', 10, 2)->nullable()->after('total_quantity_kg');
            $table->decimal('low_stock_threshold', 10, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('feed_batches', function (Blueprint $table) {
            $table->dropColumn(['brand', 'total_quantity_kg', 'unit_cost', 'low_stock_threshold']);
        });
    }
};
