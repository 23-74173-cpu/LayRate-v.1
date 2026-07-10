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
        Schema::table('forecast_input_records', function (Blueprint $table) {
            $table->dropColumn('feed_batch_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forecast_input_records', function (Blueprint $table) {
            $table->string('feed_batch_code', 50)->nullable()->after('humidity_percent');
        });
    }
};
