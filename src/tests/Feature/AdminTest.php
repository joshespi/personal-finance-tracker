<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\LoginHistory;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    // --- Access control ---

    public function test_admin_dashboard_requires_auth(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Users');
    }

    // --- User management ---

    public function test_admin_users_index_shows_all_users(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    public function test_admin_users_index_shows_verified_badge(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Unverified');
    }

    public function test_admin_can_view_user_show_page(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name'     => 'Updated Name',
                'email'    => $user->email,
                'is_admin' => '0',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_other_user(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // --- Activity log ---

    public function test_admin_can_view_activity_log(): void
    {
        $admin = $this->makeAdmin();
        ActivityLog::create([
            'user_id' => $admin->id,
            'action'  => 'login',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity'))
            ->assertOk()
            ->assertSee('Logged in');
    }

    public function test_activity_log_filters_by_user(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        ActivityLog::create(['user_id' => $admin->id, 'action' => 'portfolio.created']);
        ActivityLog::create(['user_id' => $user->id,  'action' => 'watchlist.added']);

        $response = $this->actingAs($admin)
            ->get(route('admin.activity', ['user_id' => $user->id]));

        $response->assertOk()->assertSee('watchlist.added');
    }

    // --- Login history ---

    public function test_login_creates_history_record(): void
    {
        $user = $this->makeUser();

        $this->post(route('login'), [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('login_history', ['user_id' => $user->id]);
    }

    public function test_admin_user_show_displays_login_history(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        LoginHistory::create(['user_id' => $user->id, 'ip_address' => '1.2.3.4']);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('1.2.3.4');
    }

    // --- Impersonation ---

    public function test_admin_can_impersonate_user(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $user))
            ->assertRedirect(route('dashboard'));

        $this->assertEquals($user->id, session('impersonate_user_id'));
        $this->assertEquals($admin->id, session('impersonate_admin_id'));
    }

    public function test_admin_cannot_impersonate_self(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $admin))
            ->assertForbidden();
    }

    public function test_stopping_impersonation_restores_session(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $user));

        $this->delete(route('admin.impersonate.stop'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse(session()->has('impersonate_user_id'));
    }

    public function test_impersonation_is_logged_in_activity(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $user));

        $this->assertDatabaseHas('activity_logs', [
            'action'    => 'impersonate.start',
            'user_id'   => $admin->id,
        ]);
    }

    // --- Settings ---

    public function test_admin_can_view_settings(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('registration');
    }

    public function test_admin_can_disable_registration(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), ['registration_open' => '0'])
            ->assertRedirect(route('admin.settings'));

        $this->assertEquals('0', AppSetting::get('registration_open'));
    }

    public function test_registration_blocked_when_closed(): void
    {
        AppSetting::set('registration_open', '0');

        $this->post(route('register'), [
            'name'                  => 'New User',
            'email'                 => 'new@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();
    }

    public function test_registration_page_redirects_when_closed(): void
    {
        AppSetting::set('registration_open', '0');

        $this->get(route('register'))
            ->assertRedirect(route('login'));
    }

    // --- Activity logging in controllers ---

    public function test_creating_portfolio_logs_activity(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('portfolios.store'), [
            'name'     => 'Test Portfolio',
            'currency' => 'USD',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action'  => 'portfolio.created',
        ]);
    }
}
