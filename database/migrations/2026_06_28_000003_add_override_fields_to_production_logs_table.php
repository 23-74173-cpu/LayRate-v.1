<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->foreignId('overridden_by_user_id')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable()->after('overridden_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overridden_by_user_id');
            $table->dropColumn('overridden_at');
        });
    }
};
