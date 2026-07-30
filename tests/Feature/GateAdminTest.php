<?php

namespace Tests\Feature;

use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\HardwareItem;
use App\Models\PreOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class GateAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ─── @can('admin') gate behavior ──────────────────────────────────

    public function test_admin_user_can_see_mortality_delete_button(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('chickens.mortality-records'));

        $response->assertOk();
        $response->assertSee('trash-2');
    }

    public function test_operator_user_cannot_see_mortality_delete_button(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();

        $response = $this->actingAs($operator)
            ->get(route('chickens.mortality-records'));

        $response->assertOk();
        $response->assertDontSee('data-lucide="trash-2"');
    }

    public function test_gate_returns_true_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('admin'));
    }

    public function test_gate_returns_false_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();

        $this->assertFalse($operator->isAdmin());
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($operator)->denies('admin'));
    }

    // ─── Admin-protected route access control ──────────────────────────

    public function test_preorder_destroy_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();
        $preorder = PreOrder::create([
            'customer_name'  => 'Test Customer',
            'egg_size'       => 'Medium',
            'egg_count'      => 12,
            'requested_date' => now()->addDays(3)->toDateString(),
            'status'         => 'pending',
        ]);

        $response = $this->actingAs($operator)
            ->delete(route('eggs.preorders.destroy', $preorder));

        $response->assertForbidden();
    }

    public function test_hardware_destroy_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();
        $hw = HardwareItem::firstOrFail();

        $response = $this->actingAs($operator)
            ->delete(route('hardware.destroy', $hw));

        $response->assertForbidden();
    }

    public function test_feed_batch_destroy_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();
        $batch = FeedBatch::firstOrFail();

        $response = $this->actingAs($operator)
            ->delete(route('feed.batch.destroy', $batch));

        $response->assertForbidden();
    }

    public function test_feed_consumption_destroy_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();
        $log = FeedConsumptionLog::firstOrFail();

        $response = $this->actingAs($operator)
            ->delete(route('feed.consumption.destroy', $log));

        $response->assertForbidden();
    }

    public function test_forecast_generate_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();

        $response = $this->actingAs($operator)
            ->post(route('forecast.generate'));

        $response->assertForbidden();
    }

    public function test_forecast_clear_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();

        $response = $this->actingAs($operator)
            ->post(route('forecast.clear'));

        $response->assertForbidden();
    }

    public function test_forecast_import_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();

        $response = $this->actingAs($operator)
            ->post(route('forecast.import'));

        $response->assertForbidden();
    }

    public function test_preorder_destroy_succeeds_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();
        $preorder = PreOrder::create([
            'customer_name'  => 'Test Customer',
            'egg_size'       => 'Medium',
            'egg_count'      => 12,
            'requested_date' => now()->addDays(3)->toDateString(),
            'status'         => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('eggs.preorders.destroy', $preorder));

        $response->assertStatus(302);
    }

    public function test_hardware_destroy_succeeds_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();
        $hw = HardwareItem::firstOrFail();

        $response = $this->actingAs($admin)
            ->delete(route('hardware.destroy', $hw));

        $response->assertStatus(302);
    }

    public function test_feed_batch_destroy_succeeds_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();
        $batch = FeedBatch::firstOrFail();

        $response = $this->actingAs($admin)
            ->delete(route('feed.batch.destroy', $batch));

        $response->assertStatus(302);
    }

    public function test_feed_consumption_destroy_succeeds_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();
        $log = FeedConsumptionLog::firstOrFail();

        $response = $this->actingAs($admin)
            ->delete(route('feed.consumption.destroy', $log));

        $response->assertStatus(302);
    }

    public function test_forecast_generate_succeeds_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('forecast.generate'));

        // Admin passes middleware; the controller may return validation
        // (no scope/params) or redirect — either is fine as long as not 403
        $response->assertStatus(302);
    }

    public function test_forecast_clear_succeeds_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('forecast.clear'));

        $response->assertStatus(302);
    }

    public function test_forecast_import_succeeds_for_admin(): void
    {
        $admin = User::where('email', 'admin@layrate.local')->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('forecast.import'));

        $response->assertStatus(302);
    }
}
