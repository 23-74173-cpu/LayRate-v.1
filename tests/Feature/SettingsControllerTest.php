<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'operator@layrate.local')->firstOrFail();
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
                'cols' => 20,
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
}
