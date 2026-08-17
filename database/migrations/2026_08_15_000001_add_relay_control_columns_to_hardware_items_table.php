<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Relay actuation state for `relay` hardware items.
 *
 * Semantics (mirrors the ProductionLog manual-override guard so we never
 * repeat the silent-overwrite bug):
 *   - relay_status  : authoritative relay state. In control_mode=auto it is
 *                     the last value reported by the sensor bridge; in
 *                     control_mode=manual it is the user's COMMANDED value
 *                     and bridge reports are ignored for state.
 *   - control_mode  : auto (firmware hysteresis) | manual (user override).
 *   - relay_safety  : true when the DHT22 safety default is force-blocking the
 *                     relay (fan OFF despite a MANUAL ON command). control_mode
 *                     and relay_status still hold the user's command so the UI
 *                     can show "commanded ON, currently safety-blocked".
 *   - last_changed_at/by : who last took manual control (or returned to auto).
 *   - relay_seen_at : last bridge heartbeat for liveness/staleness in the UI.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hardware_items', function (Blueprint $table) {
            $table->enum('relay_status', ['on', 'off'])->nullable()->after('last_calibration_date');
            $table->enum('control_mode', ['auto', 'manual'])->default('auto')->after('relay_status');
            $table->boolean('relay_safety')->default(false)->after('control_mode');
            $table->timestamp('last_changed_at')->nullable()->after('relay_safety');
            $table->foreignId('last_changed_by')->nullable()->after('last_changed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('relay_seen_at')->nullable()->after('last_changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('hardware_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_changed_by');
            $table->dropColumn(['relay_status', 'control_mode', 'relay_safety', 'last_changed_at', 'relay_seen_at']);
        });
    }
};
