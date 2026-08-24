<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcacheResetRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/_reset-opcache')->assertRedirect(route('login'));
    }

    public function test_non_admin_authenticated_user_gets_403(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $this->actingAs($operator)->get('/_reset-opcache')->assertForbidden();
    }

    public function test_admin_gets_success_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/_reset-opcache')
            ->assertOk()
            ->assertSee('opcache reset done');
    }
}