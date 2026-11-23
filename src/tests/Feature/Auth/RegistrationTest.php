<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'features'              => ['investing', 'cash', 'retirement'],
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        Notification::assertSentTo(User::where('email', 'test@example.com')->sole(), VerifyEmail::class);
    }

    public function test_registration_stores_the_chosen_features(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'pick@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'features'              => ['cash'],
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'pick@example.com')->sole();
        $this->assertTrue($user->hasFeature('cash'));
        $this->assertFalse($user->hasFeature('investing'));
        $this->assertFalse($user->hasFeature('retirement'));
    }

    public function test_registration_requires_at_least_one_feature(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'none@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('features');

        $this->assertGuest();
    }

    public function test_unverified_user_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }
}
