<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->string('deactivation_cause', 30)->nullable()->after('is_active')
                ->comment('Set by remove() when recording mortality; cleared after MortalityLog is created. Values: mortality, culling, removal, cage_delete');
        });
    }

    public function down(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->dropColumn('deactivation_cause');
        });
    }
};
