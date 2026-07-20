<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_show_login_page_for_guest(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_show_login_redirects_authenticated_users_to_dashboard(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();

        $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_login_with_invalid_credentials_returns_errors(): void
    {
        $this->post(route('login'), [
            'email'    => 'nonexistent@layrate.local',
            'password' => 'wrongpassword',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post(route('login'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_logout_logs_out_and_redirects_to_login(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
