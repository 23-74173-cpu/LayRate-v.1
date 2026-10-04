<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * XL egg size support: default average weight (69 g, midpoint of large
     * 65 g and jumbo 73 g; editable later in the Stocks weight inputs) and
     * the XL go-live date driving the "XL counted under Large before this
     * date" historical notes. firstOrCreate so existing deployments get the
     * keys on migrate without touching any data rows.
     */
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'egg_weight_xl'],
            ['value' => 69, 'label' => 'Average Egg Weight — XL (g)']
        );

        Setting::firstOrCreate(
            ['key' => 'egg_size_xl_start'],
            ['value' => '2026-10-05', 'label' => 'XL Egg Size Go-Live Date (YYYY-MM-DD)']
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', ['egg_weight_xl', 'egg_size_xl_start'])->delete();
    }
};
