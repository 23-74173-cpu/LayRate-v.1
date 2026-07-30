<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class);
        $this->disableCookieEncryption();
    }

    private function seededUser(string $role): User
    {
        return User::where('email', $role . '@layrate.local')->firstOrFail();
    }

    private function sessionCookie($response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                return $cookie->getValue();
            }
        }
        return null;
    }

    private function loginAndGetCookie(User $user): string
    {
        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'password',
        ]);
        $response->assertRedirect();

        $cookie = $this->sessionCookie($response);
        $this->assertNotNull($cookie, 'Login response did not set a session cookie.');

        return $cookie;
    }

    public function test_profile_page_loads_for_authenticated_user(): void
    {
        $user = $this->seededUser('operator');

        $response = $this->actingAs($user)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('Your Profile');
        $response->assertSee('Security Status');
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = $this->seededUser('operator');

        $response = $this->actingAs($user)
            ->post(route('profile.update'), [
                'name'  => 'Updated Operator',
                'email' => 'updated@layrate.local',
            ]);

        $response->assertRedirect(route('profile', ['tab' => 'profile']));

        $user->refresh();
        $this->assertEquals('Updated Operator', $user->name);
        $this->assertEquals('updated@layrate.local', $user->email);
    }

    public function test_email_update_requires_unique_email_excluding_self(): void
    {
        $operator = $this->seededUser('operator');
        $admin    = $this->seededUser('admin');

        // Operator cannot take admin's email
        $response = $this->actingAs($operator)
            ->post(route('profile.update'), [
                'name'  => 'Updated',
                'email' => $admin->email,
            ]);
        $response->assertSessionHasErrors(['email']);
        $this->assertNotEquals($admin->email, $operator->fresh()->email);

        // Operator can keep their own email
        $response = $this->actingAs($operator)
            ->post(route('profile.update'), [
                'name'  => 'Updated',
                'email' => $operator->email,
            ]);
        $response->assertRedirect(route('profile', ['tab' => 'profile']));
    }

    public function test_password_change_does_not_log_user_out(): void
    {
        $user = $this->seededUser('operator');
        $cookie = $this->loginAndGetCookie($user);

        $response = $this->withCookies([config('session.cookie') => $cookie])
            ->post(route('account.password'), [
                'current_password'        => 'password',
                'password'              => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);
        $response->assertRedirect(route('profile', ['tab' => 'settings']));

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        // Current session must remain authenticated after the password change
        $response = $this->withCookies([config('session.cookie') => $cookie])
            ->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_logout_other_devices_keeps_current_session_and_changes_password_hash(): void
    {
        $user = $this->seededUser('operator');
        $oldHash = $user->password;

        $cookie = $this->loginAndGetCookie($user);

        // Prime the session so AuthenticateSession middleware stores the password hash
        $this->withCookies([config('session.cookie') => $cookie])
            ->get(route('dashboard'))
            ->assertOk();

        $response = $this->withCookies([config('session.cookie') => $cookie])
            ->post(route('profile.logout-other-devices'), [
                'logout_password' => 'password',
            ]);
        $response->assertRedirect(route('profile', ['tab' => 'settings']));

        // The password hash in the database must change
        $user->refresh();
        $this->assertNotEquals($oldHash, $user->password);

        // The current session must remain authenticated
        $this->withCookies([config('session.cookie') => $cookie])
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_authenticate_session_middleware_invalidates_stale_password_hash(): void
    {
        $user = $this->seededUser('operator');
        $cookie = $this->loginAndGetCookie($user);

        // Prime the session so AuthenticateSession middleware stores the password hash
        $this->withCookies([config('session.cookie') => $cookie])
            ->get(route('dashboard'))
            ->assertOk();

        // Simulate a password change from another device
        $user->update(['password' => Hash::make('differentpassword123')]);

        // Reset the guard's cached user so the next request loads fresh from DB
        $this->app['auth']->guard('web')->forgetUser();

        // The current session should be invalidated because the session hash is now stale
        $this->withCookies([config('session.cookie') => $cookie])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_logout_other_devices_requires_current_password(): void
    {
        $user = $this->seededUser('operator');
        $cookie = $this->loginAndGetCookie($user);

        $response = $this->withCookies([config('session.cookie') => $cookie])
            ->post(route('profile.logout-other-devices'), [
                'logout_password' => 'wrong-password',
            ]);
        $response->assertSessionHasErrors(['logout_password']);
    }

    public function test_operator_can_access_override_pin_form(): void
    {
        $operator = $this->seededUser('operator');

        $response = $this->actingAs($operator)
            ->get(route('profile', ['tab' => 'settings']));

        $response->assertOk();
        $response->assertSee('Set Override PIN');
        $response->assertDontSee('Staff Override PIN Status');
    }

    public function test_admin_can_see_staff_pin_status_table(): void
    {
        $admin = $this->seededUser('admin');

        $response = $this->actingAs($admin)
            ->get(route('profile', ['tab' => 'settings']));

        $response->assertOk();
        $response->assertSee('Staff Override PIN Status');
    }
}
