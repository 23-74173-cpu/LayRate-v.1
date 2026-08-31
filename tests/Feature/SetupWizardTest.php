<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_renders_for_admin_when_incomplete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('setup'))
            ->assertOk()
            ->assertSee('Initial Setup')
            ->assertSee('Set the Date')
            ->assertDontSee('Farm Grid');
    }

    public function test_setup_store_saves_and_marks_complete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('setup.store'), [
            'system_time' => now()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('dashboard'));

        $this->assertEquals('1', Setting::get('setup_completed'));
    }

    public function test_dashboard_redirects_admin_to_setup_when_incomplete(): void
    {
        Setting::set('setup_completed', '0');
        Cage::query()->delete();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('setup'));
    }

    public function test_dashboard_does_not_redirect_after_setup_complete(): void
    {
        Setting::set('setup_completed', '1');
        Cage::query()->delete();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_setup_submit_requires_system_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('setup.store'), [])
            ->assertSessionHasErrors(['system_time']);
    }
}
