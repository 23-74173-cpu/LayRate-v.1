<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            ['key' => 'day_reset_time', 'value' => '06:00', 'label' => 'Day Reset Time', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // The timezone is now hardcoded to Asia/Manila and managed by the Pi's
        // system clock; remove any legacy app_timezone setting row.
        DB::table('settings')->where('key', 'app_timezone')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'day_reset_time')->delete();
    }
};
