<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cages', function (Blueprint $table) {
            $table->unsignedSmallInteger('location_row')->nullable()->after('is_active');
            $table->unsignedSmallInteger('location_column')->nullable()->after('location_row');
            $table->unique(['location_row', 'location_column'], 'cages_location_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cages', function (Blueprint $table) {
            $table->dropUnique('cages_location_unique');
            $table->dropColumn(['location_row', 'location_column']);
        });
    }
};
