<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fast device-key lookup for sensor ingestion (see DeviceAuth). Holds the
// SHA-256 of the device's API key so a request is matched with one indexed
// lookup instead of a bcrypt check (~200 ms of CPU) on every reading.
// Nullable: existing devices get it filled in the first time their key
// passes the old bcrypt check, so no device needs a new key.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('devices', 'api_key_lookup')) {
            return;
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->char('api_key_lookup', 64)->nullable()->unique()->after('api_key_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('devices', 'api_key_lookup')) {
            return;
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['api_key_lookup']);
            $table->dropColumn('api_key_lookup');
        });
    }
};
