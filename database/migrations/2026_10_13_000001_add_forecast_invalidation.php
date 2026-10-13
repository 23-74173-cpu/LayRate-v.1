<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('forecasts', fn (Blueprint $table) => $table->timestamp('invalidated_at')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('forecasts', fn (Blueprint $table) => $table->dropColumn('invalidated_at'));
    }
};
