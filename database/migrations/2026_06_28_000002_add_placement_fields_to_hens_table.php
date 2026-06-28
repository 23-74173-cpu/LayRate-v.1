<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->date('placement_date')->nullable()->after('date_acquired');
            $table->unsignedInteger('age_at_placement_weeks')->nullable()->after('placement_date');
        });
    }

    public function down(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->dropColumn(['placement_date', 'age_at_placement_weeks']);
        });
    }
};
