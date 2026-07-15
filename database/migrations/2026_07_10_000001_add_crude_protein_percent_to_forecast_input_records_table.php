<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('forecast_input_records', function (Blueprint $table) {
            $table->decimal('crude_protein_percent', 5, 2)->nullable()->after('humidity_percent');
        });
    }

    public function down(): void
    {
        Schema::table('forecast_input_records', function (Blueprint $table) {
            $table->dropColumn('crude_protein_percent');
        });
    }
};
