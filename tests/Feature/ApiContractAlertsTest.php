<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Prompt 9 — validate the new API envelope on the only converted endpoints
 * this pass (AlertController::apiIndex / apiMarkRead, DeviceAuth-gated).
 * Note: DeviceAuth's own 401 and model-binding 404s are NOT yet envelope-
 * conformant — see docs/api-contract.md §6 open items.
 */
class ApiContractAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function deviceKey(): string
    {
        $device = Device::create(['name' => 'contract-test', 'is_active' => true, 'api_key_hash' => Hash::make('placeholder')]);

        return $device->generateApiKey();
    }

    public function test_index_uses_success_envelope(): void
    {
        $key = $this->deviceKey();
        Alert::create(['alert_type' => 'temperature_high', 'message' => 't', 'is_read' => 0, 'triggered_at' => now()]);

        $this->withHeaders(['X-Device-Key' => $key])
            ->getJson('/api/alerts')
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['total' => 1]])
            ->assertJsonStructure(['success', 'data' => ['alerts' => [['id', 'alert_type', 'message', 'is_read']], 'total']]);
    }

    public function test_mark_read_uses_success_envelope(): void
    {
        $key = $this->deviceKey();
        $alert = Alert::create(['alert_type' => 'temperature_high', 'message' => 't', 'is_read' => 0, 'triggered_at' => now()]);

        $this->withHeaders(['X-Device-Key' => $key])
            ->putJson("/api/alerts/{$alert->id}/read")
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['id' => $alert->id, 'is_read' => true]]);

        $this->assertTrue((bool) $alert->fresh()->is_read);
    }

    public function test_mark_read_not_found_returns_404(): void
    {
        $key = $this->deviceKey();

        // Open item (docs/api-contract.md §6): model-binding 404s are the
        // Laravel-default {message} body, not yet {success:false,error}.
        $this->withHeaders(['X-Device-Key' => $key])
            ->putJson('/api/alerts/999999/read')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}