<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('forecasts', 'breed')) {
            Schema::table('forecasts', function (Blueprint $table) {
                $table->string('breed', 100)->nullable()->after('cage_slot_id');
            });
        }

        if (Schema::hasColumn('forecasts', 'predicted_hdep')) {
            Schema::table('forecasts', function (Blueprint $table) {
                $table->renameColumn('predicted_hdep', 'predicted_egg_count');
            });
        }

        if (Schema::hasColumn('forecasts', 'predicted_egg_count')) {
            Schema::table('forecasts', function (Blueprint $table) {
                $table->decimal('predicted_egg_count', 10, 2)->change();
            });
        } else {
            Schema::table('forecasts', function (Blueprint $table) {
                $table->decimal('predicted_egg_count', 10, 2)->nullable()->after('target_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forecasts', 'predicted_egg_count')) {
            Schema::table('forecasts', function (Blueprint $table) {
                $table->renameColumn('predicted_egg_count', 'predicted_hdep');
            });
        }

        if (Schema::hasColumn('forecasts', 'breed')) {
            Schema::table('forecasts', function (Blueprint $table) {
                $table->dropColumn('breed');
            });
        }
    }
};
