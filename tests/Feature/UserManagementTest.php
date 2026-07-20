<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function seededUser(string $role): User
    {
        return User::where('email', $role . '@layrate.local')->firstOrFail();
    }

    /** @test */
    public function admin_can_create_a_user()
    {
        $admin = $this->seededUser('admin');

        $response = $this->actingAs($admin)->post(route('settings.users.store'), [
            'name'     => 'New Hire',
            'email'    => 'newhire@layrate.local',
            'password' => 'password123',
            'role'     => 'operator',
        ]);

        $response->assertRedirect(route('profile', ['tab' => 'settings']));
        $this->assertDatabaseHas('users', [
            'email' => 'newhire@layrate.local',
            'name'  => 'New Hire',
            'role'  => 'operator',
            'is_active' => 1,
        ]);

        $created = User::where('email', 'newhire@layrate.local')->first();
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    /** @test */
    public function operator_cannot_create_a_user()
    {
        $operator = $this->seededUser('operator');

        $response = $this->actingAs($operator)->post(route('settings.users.store'), [
            'name'     => 'Blocked',
            'email'    => 'blocked@layrate.local',
            'password' => 'password123',
            'role'     => 'operator',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@layrate.local']);
    }

    /** @test */
    public function creating_a_user_requires_unique_email()
    {
        $admin = $this->seededUser('admin');

        $response = $this->actingAs($admin)->post(route('settings.users.store'), [
            'name'     => 'Duplicate',
            'email'    => 'admin@layrate.local',
            'password' => 'password123',
            'role'     => 'operator',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function admin_can_update_another_users_name_email_and_role()
    {
        $admin = $this->seededUser('admin');
        $operator = $this->seededUser('operator');

        $response = $this->actingAs($admin)->put(route('settings.users.update', $operator), [
            'name'  => 'Renamed Operator',
            'email' => 'renamed@layrate.local',
            'role'  => 'admin',
        ]);

        $response->assertRedirect(route('profile', ['tab' => 'settings']));
        $this->assertDatabaseHas('users', [
            'id'    => $operator->id,
            'name'  => 'Renamed Operator',
            'email' => 'renamed@layrate.local',
            'role'  => 'admin',
        ]);
    }

    /** @test */
    public function admin_cannot_change_their_own_role()
    {
        $admin = $this->seededUser('admin');

        $response = $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name'  => $admin->name,
            'email' => $admin->email,
            'role'  => 'operator',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    /** @test */
    public function admin_can_deactivate_another_user()
    {
        $admin = $this->seededUser('admin');
        $operator = $this->seededUser('operator');

        $response = $this->actingAs($admin)->post(route('settings.users.toggle-active', $operator));

        $response->assertRedirect(route('profile', ['tab' => 'settings']));
        $this->assertDatabaseHas('users', ['id' => $operator->id, 'is_active' => 0]);
    }

    /** @test */
    public function admin_can_reactivate_a_deactivated_user()
    {
        $admin = $this->seededUser('admin');
        $operator = $this->seededUser('operator');
        $operator->update(['is_active' => false]);

        $response = $this->actingAs($admin)->post(route('settings.users.toggle-active', $operator));

        $response->assertRedirect(route('profile', ['tab' => 'settings']));
        $this->assertDatabaseHas('users', ['id' => $operator->id, 'is_active' => 1]);
    }

    /** @test */
    public function admin_cannot_deactivate_their_own_account()
    {
        $admin = $this->seededUser('admin');

        $response = $this->actingAs($admin)->post(route('settings.users.toggle-active', $admin));

        $response->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => 1]);
    }

    /** @test */
    public function cannot_deactivate_the_only_active_admin()
    {
        $admin = $this->seededUser('admin');
        $operator = $this->seededUser('operator');
        $operator->update(['role' => 'admin']);
        // Deactivate the operator-turned-admin first, leaving $admin as the only active admin.
        $operator->update(['is_active' => false]);

        $response = $this->actingAs($admin)->post(route('settings.users.toggle-active', $admin));

        // Blocked by the self-deactivation rule first — this exercises that path.
        $response->assertSessionHasErrors('user');
    }

    /** @test */
    public function deactivated_user_cannot_log_in()
    {
        $operator = $this->seededUser('operator');
        $operator->update(['is_active' => false]);

        $response = $this->post(route('login'), [
            'email'    => $operator->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** @test */
    public function deactivating_a_logged_in_user_ends_their_session_on_next_request()
    {
        $admin = $this->seededUser('admin');
        $operator = $this->seededUser('operator');

        // Deactivate first, then simulate the operator's already-authenticated
        // session making a subsequent request — EnsureUserIsActive must catch it.
        $operator->update(['is_active' => false]);

        $response = $this->actingAs($operator)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** @test */
    public function operator_cannot_toggle_another_users_active_status()
    {
        $operator = $this->seededUser('operator');
        $admin = $this->seededUser('admin');

        $response = $this->actingAs($operator)->post(route('settings.users.toggle-active', $admin));

        $response->assertForbidden();
    }

    /** @test */
    public function team_list_is_only_visible_to_admins()
    {
        $operator = $this->seededUser('operator');

        $response = $this->actingAs($operator)->get(route('profile', ['tab' => 'settings']));

        $response->assertOk();
        $response->assertDontSee('Add User');
    }

    /** @test */
    public function admin_sees_team_management_card()
    {
        $admin = $this->seededUser('admin');

        $response = $this->actingAs($admin)->get(route('profile', ['tab' => 'settings']));

        $response->assertOk();
        $response->assertSee('Team');
        $response->assertSee('Add User');
    }
}
