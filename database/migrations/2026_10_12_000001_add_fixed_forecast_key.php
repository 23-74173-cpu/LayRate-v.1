<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->string('forecast_key', 64)->nullable()->unique();
        });

        // Retain all older runs; designate only the earliest advance prediction.
        $seen = [];
        foreach (DB::table('forecasts')->where('is_backtest', false)
            ->whereColumn('forecast_date', '<', 'target_date')
            ->orderBy('forecast_date')->orderBy('id')->lazy(500) as $forecast) {
            $key = hash('sha256', json_encode([
                $forecast->cage_id === null ? null : (int) $forecast->cage_id,
                $forecast->breed,
                substr($forecast->target_date, 0, 10),
            ]));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            DB::table('forecasts')->where('id', $forecast->id)->update(['forecast_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropUnique(['forecast_key']);
            $table->dropColumn('forecast_key');
        });
    }
};
