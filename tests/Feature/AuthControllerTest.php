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

    // ── Back/Forward after sign-in / sign-out (PreventBackHistory + auth-guard.js) ──

    private function assertNoStore($response): void
    {
        $cache = (string) $response->headers->get('Cache-Control');
        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cache);
        }
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    public function test_authenticated_pages_are_never_cached(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();

        $this->assertNoStore($this->actingAs($user)->get(route('dashboard')));
        $this->assertNoStore($this->actingAs($user)->get(route('profile')));
    }

    public function test_login_and_info_pages_are_never_cached(): void
    {
        $this->assertNoStore($this->get(route('login'))->assertOk());
        $this->assertNoStore($this->get(route('landing'))->assertOk());
    }

    public function test_guest_is_sent_to_login_from_protected_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_info_page_redirects_authenticated_users_to_dashboard(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();

        $this->actingAs($user)->get(route('landing'))->assertRedirect(route('dashboard'));
    }

    public function test_after_logout_protected_pages_redirect_to_login(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();

        $login = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => '1']);
        $this->assertAuthenticated();
        $tokenBefore = $user->fresh()->remember_token;
        $remember = collect($login->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($remember);

        // The test client doesn't replay cookies; send the remember-me cookie
        // back like a browser would.
        $logout = $this->withCookie($remember->getName(), $login->getCookie($remember->getName())->getValue())
            ->post(route('logout'))->assertRedirect(route('login'));
        $this->assertNoStore($logout);
        $this->assertGuest();

        // Remember-me cookie is expired and its token rotated, so it can't
        // sign the browser back in.
        $recaller = collect($logout->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($recaller);
        $this->assertTrue($recaller->isCleared());
        $this->assertNotSame($tokenBefore, $user->fresh()->remember_token);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_auth_status_reports_session_state(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();

        $guest = $this->getJson(route('auth.status'))->assertOk()->assertExactJson(['authenticated' => false]);
        $this->assertNoStore($guest);

        $this->actingAs($user)->getJson(route('auth.status'))
            ->assertOk()->assertExactJson(['authenticated' => true]);
    }

    public function test_auth_status_ends_session_of_deactivated_user(): void
    {
        $user = User::where('email', 'operator@layrate.local')->firstOrFail();
        $user->forceFill(['is_active' => false])->save();

        // EnsureUserIsActive signs them out and redirects; auth-guard.js
        // treats a redirect from /auth/status as "signed out".
        $this->actingAs($user)->get(route('auth.status'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
