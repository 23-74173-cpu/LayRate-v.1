<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'operator@layrate.local')->firstOrFail();
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
    }

    public function test_store_farm_layout_updates_settings(): void
    {
        $this->actingAs($this->user)
            ->post(route('settings.farm-layout'), [
                'rows' => 3,
                'cols' => 4,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertEquals('3', Setting::get('farm_grid_rows'));
        $this->assertEquals('4', Setting::get('farm_grid_cols'));
    }

    public function test_store_farm_layout_rejects_invalid_rows_and_cols(): void
    {
        $this->actingAs($this->user)
            ->post(route('settings.farm-layout'), [
                'rows' => 0,
                'cols' => 51,
            ])
            ->assertSessionHasErrors(['rows', 'cols']);
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->post(route('settings.farm-layout'), [
            'rows' => 3,
            'cols' => 4,
        ])->assertRedirect(route('login'));
    }

    public function test_profile_settings_page_shows_system_time_to_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('profile', ['tab' => 'settings']))
            ->assertOk()
            ->assertSeeText('System Time');
    }

    public function test_profile_settings_page_hides_system_time_from_operator(): void
    {
        $this->actingAs($this->user)
            ->get(route('profile', ['tab' => 'settings']))
            ->assertOk()
            ->assertDontSeeText('System Time');
    }

    public function test_admin_can_view_system_time_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.system-time'))
            ->assertOk()
            ->assertSeeText('Set System Time');
    }

    public function test_operator_cannot_view_system_time_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('settings.system-time'))
            ->assertForbidden();
    }
}
