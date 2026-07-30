<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $indexes = DB::select('SHOW INDEX FROM cages WHERE Key_name = ?', ['cages_location_unique']);
        if (!empty($indexes)) {
            Schema::table('cages', function ($table) {
                $table->dropUnique('cages_location_unique');
            });
        }
    }

    public function down(): void
    {
        $indexes = DB::select('SHOW INDEX FROM cages WHERE Key_name = ?', ['cages_location_unique']);
        if (empty($indexes)) {
            Schema::table('cages', function ($table) {
                $table->unique(['location_row', 'location_column'], 'cages_location_unique');
            });
        }
    }
};
