<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Split the shared 'low_stock' alert type into its two real meanings:
     *   - 'low_stock_feed'  — a feed batch's remaining kg fell to its threshold
     *                         (FeedController::checkLowStock).
     *   - 'low_stock_eggs'  — an egg-size pool fell to its threshold
     *                         (EggStockBatch::checkLowStock).
     *
     * Existing rows were created by exactly one of those two producers, so the
     * message text is a reliable discriminator: egg-stock messages always read
     * "Low stock: {Size} eggs — {n} remaining (threshold: …)" (i.e. contain a
     * size name / " eggs — "), while feed messages read
     * "Low stock: {batch_code} — {n} kg remaining (threshold: …)".
     */
    public function up(): void
    {
        DB::table('alerts')
            ->where('alert_type', 'low_stock')
            ->orderBy('id')
            ->select(['id', 'message'])
            ->get()
            ->each(function ($alert) {
                $message = strtolower($alert->message);
                $type = preg_match('/\b(?:small|medium|large|jumbo|unsorted)\s+eggs?\b/', $message)
                        || str_contains($message, 'eggs — ')
                    ? 'low_stock_eggs'
                    : 'low_stock_feed';

                DB::table('alerts')->where('id', $alert->id)->update(['alert_type' => $type]);
            });
    }

    public function down(): void
    {
        DB::table('alerts')
            ->whereIn('alert_type', ['low_stock_feed', 'low_stock_eggs'])
            ->update(['alert_type' => 'low_stock']);
    }
};