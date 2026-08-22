<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\Setting;

/**
 * Hardware health state machine (Prompt 6 implementation).
 *
 * `health_state` (online/stale/disconnected/faulty/recovering/unknown) is the
 * runtime truth; admin `status` (active/spare/faulty/removed) is untouched
 * and stays the admin lifecycle — the two may legitimately disagree (see the
 * design report §5.6). This evaluator never writes `status`, so ingestion is
 * never permanently gated and sensors can recover automatically.
 *
 * Cadence (§5.2): ingestion-triggered re-evaluation on each raw reading (a
 * fresh valid reading ⇒ online immediately) + a 15-min backstop for
 * elapsed-time escalations (runStalenessBackstop), replacing
 * CheckHardwareSensorStaleness.
 */
class DeviceHealthEvaluator
{
    public const STATE_UNKNOWN = 'unknown';
    public const STATE_ONLINE = 'online';
    public const STATE_STALE = 'stale';
    public const STATE_DISCONNECTED = 'disconnected';
    public const STATE_FAULTY = 'faulty';
    public const STATE_RECOVERING = 'recovering';

    public const FAULT_IMPLAUSIBLE = 'dht_implausible';
    public const FAULT_STUCK = 'dht_stuck';
    public const FAULT_IR_JUMP = 'ir_impossible_jump';
    public const FAULT_BRIDGE_OFFLINE = 'bridge_offline';
    public const FAULT_RELAY_ADVISORY = 'relay_safety_advisory';

    private function threshold(string $key, $default): float
    {
        return (float) Setting::get($key, $default);
    }

    private function intSetting(string $key, $default): int
    {
        return (int) Setting::get($key, $default);
    }

    private function debounceTicks(): int
    {
        return $this->intSetting('health_debounce_ticks', 3);
    }

    private function recoveryTicks(): int
    {
        return $this->intSetting('health_recovery_ticks', 3);
    }

    /**
     * Stuck-value fingerprint — temp/hum components occupy disjoint ranges
     * (temp·10 in the thousands slot, hum·10 in 0..999), so two genuinely
     * different (temp, hum) readings can never share a signature.
     */
    public static function fingerprint(float $temp, float $hum): int
    {
        return ((int) round($temp * 10)) * 1000 + (int) round($hum * 10);
    }

    // ── Ingestion-triggered registration (raw rows only; overrides never call) ──

    public function registerDhtReading(HardwareItem $item, float $temp, float $hum): void
    {
        $sig = self::fingerprint($temp, $hum);
        $same = ($item->reading_signature === $sig) ? (int) $item->consecutive_same_readings + 1 : 1;

        $fault = $this->implausibleDht($temp, $hum);
        $stuckReadings = $this->intSetting('health_dht_stuck_readings', 8);
        if ($fault === null && $same >= $stuckReadings) {
            $fault = self::FAULT_STUCK;
        }

        $item->update([
            'reading_signature'          => $sig,
            'consecutive_same_readings'  => $same,
            'last_valid_reading_at'      => now(),
        ]);
        $item->fresh();

        $this->evaluate($item, forceOnlineAfterValid: true, forcedFault: $fault, recordValid: $fault === null);
    }

    public function registerIrReading(HardwareItem $item, int $reportedCount, ?CageSlot $slot = null): void
    {
        $prev = $item->latestOccupancyReading?->reported_count;
        $capacity = ($slot ?? $item->cageSlot)?->max_chickens_per_slot ?? 0;

        $fault = null;
        if ($prev !== null && $capacity > 0 && abs($reportedCount - $prev) > $capacity) {
            $fault = self::FAULT_IR_JUMP;
        }

        $item->update(['last_valid_reading_at' => now()]);
        $item->fresh();

        $this->evaluate($item, forceOnlineAfterValid: true, forcedFault: $fault, recordValid: $fault === null);
    }

    public function registerRelaySeen(HardwareItem $relay, ?EnvironmentalLog $cageEnvLog = null): void
    {
        $relay->update(['last_valid_reading_at' => now()]);
        $relay->fresh();

        // §5.4 — advisory only: DHT implausibility for the relay's cage may mark
        // health faulty + raise health_safety_advisory, but NEVER touches the
        // firmware safety path (relay_safety / OFF (SAFETY)).
        $advisory = null;
        if ($cageEnvLog && $this->implausibleDht((float) $cageEnvLog->temperature_c, (float) $cageEnvLog->humidity_pct) !== null) {
            $advisory = self::FAULT_RELAY_ADVISORY;
        }

        $this->evaluate($relay, forceOnlineAfterValid: true, forcedFault: $advisory, recordValid: $advisory === null, relayAdvisory: $advisory !== null);
    }

    public function registerDeviceHeartbeat(Device $device): void
    {
        $device->update(['last_seen_at' => now()]);
    }

    // ── Evaluation (elapsed-time + forced transitions) ──

    public function evaluate(
        HardwareItem $item,
        bool $forceOnlineAfterValid = false,
        ?string $forcedFault = null,
        bool $recordValid = true,
        bool $relayAdvisory = false,
    ): void {
        if ($item->status !== 'active') {
            $this->setUnknownIfNeeded($item);
            return;
        }

        if ($forcedFault !== null) {
            $this->transition($item, self::STATE_FAULTY, $forcedFault);
            return;
        }

        if ($forceOnlineAfterValid) {
            $this->handleFreshReading($item, $recordValid);
            return;
        }

        $this->handleLiveness($item);
    }

    private function handleFreshReading(HardwareItem $item, bool $recordValid): void
    {
        $current = $item->health_state ?: self::STATE_UNKNOWN;

        if ($current === self::STATE_FAULTY) {
            // start recovery: one recovering notice, then valid ticks count up
            $item->update(['health_state' => self::STATE_RECOVERING, 'health_debounce_run' => 1, 'fault_issue' => null]);
            $this->emitStateAlert($item, self::STATE_RECOVERING);
            return;
        }

        if ($current === self::STATE_RECOVERING) {
            $run = (int) $item->health_debounce_run + 1;
            if ($run >= $this->recoveryTicks()) {
                $item->update(['health_state' => self::STATE_ONLINE, 'health_debounce_run' => 0]);
            } else {
                $item->update(['health_debounce_run' => $run]);
            }
            return;
        }

        if (! $recordValid) {
            // data was recorded but not valid (e.g. advisory) — keep current state
            return;
        }

        // any other state + a fresh valid reading → online immediately (§5.2)
        $item->update(['health_state' => self::STATE_ONLINE, 'health_debounce_run' => 0, 'fault_issue' => null]);
    }

    private function handleLiveness(HardwareItem $item): void
    {
        $last = $item->last_valid_reading_at;
        if (! $last) {
            $this->setUnknownIfNeeded($item);
            return;
        }

        $ageMin = max(0.0, (float) $last->diffInMinutes(now()));

        [$online, $stale, $disconnected] = $this->windows($item->device_type);

        if ($ageMin <= $online) {
            $target = self::STATE_ONLINE;
        } elseif ($ageMin <= $stale) {
            $target = self::STATE_STALE;
        } else {
            $target = self::STATE_DISCONNECTED;
        }

        $this->transition($item, $target);
    }

    private function windows(string $type): array
    {
        return match ($type) {
            'IR_breakbeam' => [
                $this->threshold('health_ir_online_min', 15),
                $this->threshold('health_ir_stale_min', 1440),
                $this->threshold('health_ir_disconnected_min', 1440),
            ],
            'relay' => [
                $this->threshold('health_relay_online_min', 2),
                $this->threshold('health_relay_stale_min', 10),
                $this->threshold('health_relay_disconnected_min', 10),
            ],
            default => [
                $this->threshold('health_dht_online_min', 5),
                $this->threshold('health_dht_stale_min', 60),
                $this->threshold('health_dht_disconnected_min', 60),
            ],
        };
    }

    // ── Debounced transition + recovery ──

    private function transition(HardwareItem $item, string $target, ?string $faultIssue = null): void
    {
        $current = $item->health_state ?: self::STATE_UNKNOWN;

        if ($current === $target) {
            $payload = ['health_debounce_run' => 0];
            if ($faultIssue !== null && $item->fault_issue !== $faultIssue) {
                $payload['fault_issue'] = $faultIssue;
            }
            $item->update($payload);
            return;
        }

        $run = (int) $item->health_debounce_run + 1;
        if ($run >= $this->debounceTicks()) {
            $payload = ['health_state' => $target, 'health_debounce_run' => 0];
            if ($faultIssue !== null) {
                $payload['fault_issue'] = $faultIssue;
            }
            $item->update($payload);
            $this->emitStateAlert($item, $target, $faultIssue);
        } else {
            $item->update(['health_debounce_run' => $run]);
        }
    }

    private function setUnknownIfNeeded(HardwareItem $item): void
    {
        if ($item->health_state !== self::STATE_UNKNOWN) {
            $item->update(['health_state' => self::STATE_UNKNOWN, 'health_debounce_run' => 0]);
        }
    }

    // ── Alerting (§5.3 / §5.4: createDeduped only) ──

    private function emitStateAlert(HardwareItem $item, string $state, ?string $faultIssue = null): void
    {
        // §5.4 relay advisory uses its own alert type (dedup key
        // {cage|0}:health_safety_advisory) — observability only.
        $type = $faultIssue === self::FAULT_RELAY_ADVISORY
            ? 'health_safety_advisory'
            : 'health_' . $state;
        $cageId = $item->cage_id ?? $item->cageSlot?->cage_id;
        $issue = $item->fault_issue ?: 'unknown cause';

        $message = match ($state) {
            self::STATE_STALE => "Sensor {$item->serial_number} has not reported recently (stale).",
            self::STATE_DISCONNECTED => "Sensor {$item->serial_number} is disconnected ({$issue}).",
            self::STATE_FAULTY => "Sensor {$item->serial_number} is faulty ({$issue}).",
            self::STATE_RECOVERING => "Sensor {$item->serial_number} is showing valid data again — recovering.",
            default => "Sensor {$item->serial_number} health: {$state}.",
        };

        Alert::createDeduped([
            'cage_id'      => $cageId,
            'alert_type'   => $type,
            'message'      => $message,
            'is_read'      => 0,
            'triggered_at' => now(),
            'dedup_key'    => Alert::dedupKey($cageId, $type),
            'alert_day'    => ReportingDateService::reportingDateString(),
        ]);
    }

    // ── 15-min backstop (replaces hardware:check-staleness) ──

    public function runStalenessBackstop(): void
    {
        $maxDisconnected = [
            'DHT22'        => $this->threshold('health_dht_disconnected_min', 60),
            'IR_breakbeam' => $this->threshold('health_ir_disconnected_min', 1440),
            'relay'        => $this->threshold('health_relay_disconnected_min', 10),
        ];

        // Single-cause bridge-offline propagation.
        Device::where('is_active', true)->each(function (Device $device) use ($maxDisconnected) {
            $seen = $device->last_seen_at;
            if (! $seen) {
                return;
            }
            $bridgeOffline = max(0.0, (float) $seen->diffInMinutes(now())) > ($maxDisconnected['DHT22'] ?? 60);
            if (! $bridgeOffline) {
                return;
            }
            foreach ($device->hardwareItems()->where('status', 'active')->whereNotNull('health_state')->cursor() as $item) {
                if ($item->health_state !== self::STATE_DISCONNECTED) {
                    $this->transition($item, self::STATE_DISCONNECTED, self::FAULT_BRIDGE_OFFLINE);
                }
            }
        });

        // Per-item elapsed evaluation.
        HardwareItem::where('status', 'active')->each(function (HardwareItem $item) {
            $this->evaluate($item);
        });
    }

    private function implausibleDht(float $temp, float $hum): ?string
    {
        $tMin = $this->threshold('health_dht_range_temp_min', 0);
        $tMax = $this->threshold('health_dht_range_temp_max', 50);
        $hMin = $this->threshold('health_dht_range_hum_min', 10);
        $hMax = $this->threshold('health_dht_range_hum_max', 95);

        if ($temp < $tMin || $temp > $tMax || $hum < $hMin || $hum > $hMax) {
            return self::FAULT_IMPLAUSIBLE;
        }

        return null;
    }
}