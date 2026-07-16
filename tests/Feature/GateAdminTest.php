<?php

namespace Tests\Feature;

use App\Models\MortalityLog;
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
}
