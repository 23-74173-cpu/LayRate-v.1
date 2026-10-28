<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class QuickActionsDockTest extends TestCase
{
    public function test_page_action_fab_renders_without_cages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('feed'));

        $response->assertOk()
            ->assertSee('id="quickDock"', false)
            ->assertSee('class="fab-toggle mobile-dock-button', false)
            ->assertSee('aria-label="Quick actions"', false)
            ->assertSee('data-feed-action="consumption"', false)
            ->assertSee('data-feed-action="farm"', false)
            ->assertSee('data-dock-action-list', false)
            ->assertSee('data-dock-checklist-unavailable', false);
    }

    public function test_page_fab_actions_are_present_for_cages_and_egg_management(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get(route('cages.index'))
            ->assertOk()
            ->assertSee('id="quickDock"', false)
            ->assertSee('data-dock-action-list', false)
            ->assertSee('Bulk Add Hens');

        $this->actingAs($user)->get(route('eggs.logging'))
            ->assertOk()
            ->assertSee('id="quickDock"', false)
            ->assertSee('data-dock-action-list', false)
            ->assertSee('id="egg-fab-actions-stocks"', false)
            ->assertSee('id="egg-fab-actions-preorders"', false);
    }
}
