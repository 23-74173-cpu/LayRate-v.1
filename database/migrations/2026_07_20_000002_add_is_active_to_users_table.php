<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deactivating a user must not delete their row: 9 tables reference
     * users.id via recorded_by/overridden_by_user_id with ON DELETE SET
     * NULL, so a hard delete would silently erase who-did-what across the
     * whole audit trail. is_active preserves history while blocking login.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
