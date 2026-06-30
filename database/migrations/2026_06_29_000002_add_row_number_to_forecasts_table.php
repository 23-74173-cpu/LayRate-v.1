<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->unsignedInteger('row_number')->nullable()->after('cage_id');
        });
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropColumn('row_number');
        });
    }
};
