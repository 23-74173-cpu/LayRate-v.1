<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\Device;
use App\Models\HardwareItem;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RelayControlTest extends TestCase
{
    private User $admin;
    private Device $device;
    private Device $otherDevice;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@layrate.local')->first();

        $this->cage = Cage::create([
            'cage_code' => 'CAGE-RELAY',
            'location' => 'Relay test',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 4,
            'is_active' => 1,
        ]);

        $this->device = Device::create([
            'name' => 'Relay Pi',
            'api_key_hash' => Hash::make('placeholder'),
            'is_active' => true,
        ]);

        $this->otherDevice = Device::create([
            'name' => 'Other Pi',
            'api_key_hash' => Hash::make('other-key'),
            'is_active' => true,
        ]);
    }

    private function deviceKey(): string
    {
        $plain = 'lr_relay_test_key';
        $this->device->update(['api_key_hash' => Hash::make($plain)]);

        return $plain;
    }

    private function relayItem(array $overrides = []): HardwareItem
    {
        return HardwareItem::create(array_merge([
            'device_type' => 'relay',
            'serial_number' => 'RELAY-TEST-001',
            'cage_id' => $this->cage->id,
            'device_id' => $this->device->id,
            'status' => 'active',
        ], $overrides));
    }

    private function postReadings(array $payload, ?string $key = null)
    {
        $headers = $key !== null ? ['X-Device-Key' => $key] : [];

        return $this->postJson('/api/sensor-readings', $payload, $headers);
    }

    // Loose comparison on purpose: JSON encodes integral floats (30.0) as the
    // int 30, so assertJsonPath's strict same-type check would spuriously fail.
    private function assertRelayThresholds($response, float $on, float $off): void
    {
        $this->assertEquals($on, $response->json('relay.on_temp'));
        $this->assertEquals($off, $response->json('relay.off_temp'));
    }

    // ── Ingestion ────────────────────────────────────────────────

    public function test_relay_reading_in_auto_mode_updates_status_and_seen_at(): void
    {
        $item = $this->relayItem();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'on'],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();
        $response->assertJsonPath('accepted', 1);
        $response->assertJsonPath('processed.0.override_skipped', false);

        $this->assertDatabaseHas('hardware_items', [
            'id' => $item->id,
            'relay_status' => 'on',
            'control_mode' => 'auto',
        ]);
        $this->assertNotNull($item->fresh()->relay_seen_at);
    }

    public function test_manual_override_is_never_silently_overwritten(): void
    {
        // User manually turned the fan ON earlier.
        $item = $this->relayItem(['control_mode' => 'manual', 'relay_status' => 'on']);
        $key = $this->deviceKey();

        // Bridge reports OFF (auto-hysteresis would have shut it down), but the
        // manual override must be preserved — the silent-overwrite bug guard.
        $response = $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'off'],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();
        $response->assertJsonPath('accepted', 1);
        $response->assertJsonPath('processed.0.override_skipped', true);

        $fresh = $item->fresh();
        $this->assertSame('on', $fresh->relay_status, 'Manual state must survive bridge reports.');
        $this->assertSame('manual', $fresh->control_mode);
        $this->assertNotNull($fresh->relay_seen_at, 'Liveness still tracked during override.');
    }

    public function test_relay_reading_requires_relay_status_field(): void
    {
        $this->relayItem();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001'],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertStatus(422);
        $response->assertJsonPath('accepted', 0);
    }

    public function test_relay_reading_with_invalid_status_is_rejected(): void
    {
        $this->relayItem();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'maybe'],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertUnprocessable();
    }

    // ── Safety default (invalid DHT22 forces relay OFF) ──────────

    public function test_manual_on_with_safety_block_keeps_command_and_marks_blocked(): void
    {
        // User commanded MANUAL ON.
        $item = $this->relayItem(['control_mode' => 'manual', 'relay_status' => 'on']);
        $key = $this->deviceKey();

        // Bridge reports "OFF (SAFETY)" — DHT22 read invalid, fan forced off.
        $response = $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'off', 'relay_safety' => true],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();
        $response->assertJsonPath('processed.0.relay_safety', true);

        $fresh = $item->fresh();
        // Command preserved — NOT silently overwritten, NOT reverted to auto.
        $this->assertSame('manual', $fresh->control_mode);
        $this->assertSame('on', $fresh->relay_status, 'Commanded state must survive a safety block.');
        // Safety-blocked flag recorded so the UI shows the distinct state.
        $this->assertTrue($fresh->relay_safety);
        $this->assertNotNull($fresh->relay_seen_at);
    }

    public function test_manual_off_plus_safety_report_is_not_a_block(): void
    {
        // User commanded MANUAL OFF — safety forcing off is consistent, not a block.
        $item = $this->relayItem(['control_mode' => 'manual', 'relay_status' => 'off']);
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'off', 'relay_safety' => true],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();

        $fresh = $item->fresh();
        $this->assertSame('manual', $fresh->control_mode);
        $this->assertSame('off', $fresh->relay_status);
        $this->assertFalse($fresh->relay_safety, 'No conflict — commanded off, so not blocked.');
    }

    public function test_safety_block_clears_when_normal_state_is_reported(): void
    {
        $item = $this->relayItem([
            'control_mode' => 'manual',
            'relay_status' => 'on',
            'relay_safety' => true,
        ]);
        $key = $this->deviceKey();

        // DHT22 recovered — firmware reports a normal ON again.
        $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'on', 'relay_safety' => false],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key)->assertOk();

        $fresh = $item->fresh();
        $this->assertSame('manual', $fresh->control_mode);
        $this->assertSame('on', $fresh->relay_status);
        $this->assertFalse($fresh->relay_safety, 'Safety flag must clear once the reading is valid.');
    }

    public function test_auto_mode_safety_off_is_treated_as_normal_off(): void
    {
        $item = $this->relayItem(['control_mode' => 'auto']);
        $key = $this->deviceKey();

        $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'off', 'relay_safety' => true],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key)->assertOk();

        $fresh = $item->fresh();
        $this->assertSame('auto', $fresh->control_mode);
        $this->assertSame('off', $fresh->relay_status);
        $this->assertFalse($fresh->relay_safety, 'Safety-blocked display is manual-only; auto shows a plain off.');
    }

    // ── Command endpoint (bridge polling) ────────────────────────

    public function test_command_endpoint_returns_manual_command(): void
    {
        $this->relayItem(['control_mode' => 'manual', 'relay_status' => 'on']);
        $key = $this->deviceKey();

        $this->getJson('/api/relay/command', ['X-Device-Key' => $key])
            ->assertOk()
            ->assertJsonPath('relay.serial_number', 'RELAY-TEST-001')
            ->assertJsonPath('relay.mode', 'manual')
            ->assertJsonPath('relay.command', 'on');
    }

    public function test_command_endpoint_returns_auto_for_hysteresis(): void
    {
        $this->relayItem(); // control_mode defaults to auto
        $key = $this->deviceKey();

        $this->getJson('/api/relay/command', ['X-Device-Key' => $key])
            ->assertOk()
            ->assertJsonPath('relay.mode', 'auto')
            ->assertJsonPath('relay.command', 'auto');
    }

    public function test_command_endpoint_returns_null_when_no_active_relay(): void
    {
        $key = $this->deviceKey();

        $this->getJson('/api/relay/command', ['X-Device-Key' => $key])
            ->assertOk()
            ->assertJsonPath('relay', null);
    }

    public function test_command_endpoint_is_scoped_to_the_authenticated_device(): void
    {
        $this->relayItem();
        $this->otherDevice->update(['api_key_hash' => Hash::make('other-key-now')]);

        $this->getJson('/api/relay/command', ['X-Device-Key' => 'other-key-now'])
            ->assertOk()
            ->assertJsonPath('relay', null);
    }

    public function test_command_endpoint_requires_device_key(): void
    {
        $this->getJson('/api/relay/command')->assertUnauthorized();
    }

    // ── AUTO hysteresis threshold source (temp_max → THRESH) ────

    public function test_command_endpoint_exposes_default_thresholds(): void
    {
        $this->relayItem();
        $key = $this->deviceKey();

        // No settings saved yet → Setting::thresholds() defaults (temp_max=30).
        $response = $this->getJson('/api/relay/command', ['X-Device-Key' => $key]);
        $response->assertOk();
        $this->assertRelayThresholds($response, 30.0, 25.0); // off = temp_max minus 5C dead-band
    }

    public function test_command_endpoint_follows_saved_temp_max(): void
    {
        $this->relayItem();
        $key = $this->deviceKey();

        Setting::set('temp_max', 34.5);

        $response = $this->getJson('/api/relay/command', ['X-Device-Key' => $key]);
        $response->assertOk();
        $this->assertRelayThresholds($response, 34.5, 29.5);
    }

    public function test_threshold_change_flows_through_the_web_save(): void
    {
        $this->relayItem();
        $key = $this->deviceKey();

        // The Environment page save writes to settings; the bridge polls them
        // live, so the next command poll picks the change up automatically.
        $this->actingAs($this->admin)
            ->postJson('/environment/thresholds', [
                'temp_min' => 24,
                'temp_max' => 33,
                'hum_min' => 40,
                'hum_max' => 70,
            ])
            ->assertOk();

        $response = $this->getJson('/api/relay/command', ['X-Device-Key' => $key]);
        $response->assertOk();
        $this->assertRelayThresholds($response, 33.0, 28.0);
    }

    public function test_manual_control_still_ignores_thresholds(): void
    {
        // In manual mode the relay payload still carries the thresholds (the
        // bridge needs them to re-apply after returning to AUTO), but the
        // command must be the manual override, never "auto".
        $this->relayItem(['control_mode' => 'manual', 'relay_status' => 'off']);
        $key = $this->deviceKey();
        Setting::set('temp_max', 40.0);

        $response = $this->getJson('/api/relay/command', ['X-Device-Key' => $key]);
        $response->assertOk()
            ->assertJsonPath('relay.mode', 'manual')
            ->assertJsonPath('relay.command', 'off');
        $this->assertEquals(40.0, $response->json('relay.on_temp'));
    }

    public function test_safety_block_is_unaffected_by_threshold_source(): void
    {
        // Configure AUTO thresholds AND command a manual ON that then gets
        // safety-blocked — the threshold source must not change safety behavior.
        $item = $this->relayItem(['control_mode' => 'manual', 'relay_status' => 'on']);
        $key = $this->deviceKey();
        Setting::set('temp_max', 40.0);

        $this->postReadings([
            'readings' => [
                ['serial_number' => 'RELAY-TEST-001', 'relay_status' => 'off', 'relay_safety' => true],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key)->assertOk()->assertJsonPath('processed.0.relay_safety', true);

        $fresh = $item->fresh();
        $this->assertTrue($fresh->relay_safety);
        $this->assertSame('on', $fresh->relay_status);
        $this->assertSame('manual', $fresh->control_mode);
    }

    // ── Manual control (web) ─────────────────────────────────────

    public function test_web_control_sets_manual_override(): void
    {
        $item = $this->relayItem();

        $response = $this->actingAs($this->admin)
            ->postJson('/environment/relay', ['action' => 'on']);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('relay.control_mode', 'manual');
        $response->assertJsonPath('relay.relay_status', 'on');

        $this->assertDatabaseHas('hardware_items', [
            'id' => $item->id,
            'control_mode' => 'manual',
            'relay_status' => 'on',
            'last_changed_by' => $this->admin->id,
        ]);
        $this->assertNotNull($item->fresh()->last_changed_at);
    }

    public function test_web_control_turns_off(): void
    {
        $item = $this->relayItem(['control_mode' => 'manual', 'relay_status' => 'on']);

        $this->actingAs($this->admin)
            ->postJson('/environment/relay', ['action' => 'off'])
            ->assertOk()
            ->assertJsonPath('relay.relay_status', 'off')
            ->assertJsonPath('relay.control_mode', 'manual');

        $this->assertSame('off', $item->fresh()->relay_status);
    }

    public function test_web_control_returns_to_auto(): void
    {
        $item = $this->relayItem([
            'control_mode' => 'manual',
            'relay_status' => 'on',
            'relay_safety' => true,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/environment/relay', ['action' => 'auto'])
            ->assertOk()
            ->assertJsonPath('relay.control_mode', 'auto')
            ->assertJsonPath('relay.relay_safety', false);

        $fresh = $item->fresh();
        $this->assertSame('auto', $fresh->control_mode);
        $this->assertSame('on', $fresh->relay_status, 'Last known state kept; next bridge report refreshes it.');
        $this->assertFalse($fresh->relay_safety, 'Returning to AUTO clears any stale safety block.');
    }

    public function test_web_control_rejects_unknown_action(): void
    {
        $this->relayItem();

        $this->actingAs($this->admin)
            ->postJson('/environment/relay', ['action' => 'turbo'])
            ->assertUnprocessable();
    }

    public function test_web_control_without_relay_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/environment/relay', ['action' => 'on'])
            ->assertNotFound();
    }

    public function test_web_control_requires_authentication(): void
    {
        // JSON request → 401 (auth middleware rejects XHR before controller)
        $this->postJson('/environment/relay', ['action' => 'on'])->assertUnauthorized();

        // Plain form post → redirect to login
        $this->post('/environment/relay', ['action' => 'on'])->assertRedirect(route('login'));
    }

    public function test_environment_page_renders_relay_card(): void
    {
        $item = $this->relayItem([
            'control_mode' => 'manual',
            'relay_status' => 'on',
            'relay_safety' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/environment');

        $response->assertOk();
        $response->assertSee('Cooling Fan');
        $response->assertSee('relayCard');
        $response->assertSee('/environment/relay-stream');
        $response->assertSee('RELAY-TEST-001');
        // Initial render carries the safety flag so the widget can show the
        // distinct "Safety Block" state before the first SSE frame.
        $response->assertSee('relay_safety');
        $this->assertTrue($item->fresh()->relay_safety);
    }
}
