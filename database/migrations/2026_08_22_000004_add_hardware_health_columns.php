<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hardware health state machine (Prompt 6 implementation).
     *
     * hardware_items gains the runtime health model; the human lifecycle
     * `status` column is untouched (active/spare/faulty/removed remain admin-
     * driven). health_state is the machine: online/stale/disconnected/faulty/
     * recovering/unknown — the evaluator no longer flips `status` to faulty
     * (that was the "terminal sensor" bug), so ingestion continues and
     * recovery is possible without admin edits.
     *
     * reading_signature / consecutive_same_readings are the stuck-value
     * fingerprint (rounded temp*10+hum signature + run-length counter).
     */
    public function up(): void
    {
        Schema::table('hardware_items', function (Blueprint $table) {
            $table->string('health_state', 24)->default('unknown')->after('status');
            $table->dateTime('last_valid_reading_at')->nullable()->after('health_state');
            $table->string('fault_issue')->nullable()->after('last_valid_reading_at');
            $table->unsignedInteger('reading_signature')->nullable()->after('fault_issue');
            $table->unsignedTinyInteger('consecutive_same_readings')->default(0)->after('reading_signature');
            $table->unsignedTinyInteger('health_debounce_run')->default(0)->after('consecutive_same_readings');
            $table->index('health_state');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dateTime('last_seen_at')->nullable()->after('api_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('hardware_items', function (Blueprint $table) {
            $table->dropIndex(['health_state']);
            $table->dropColumn([
                'health_state',
                'last_valid_reading_at',
                'fault_issue',
                'reading_signature',
                'consecutive_same_readings',
                'health_debounce_run',
            ]);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};