<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            ['key' => 'farm_grid_rows', 'value' => '4', 'label' => 'Farm Grid Rows', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'farm_grid_cols', 'value' => '4', 'label' => 'Farm Grid Columns', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['farm_grid_rows', 'farm_grid_cols'])->delete();
    }
};
