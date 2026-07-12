<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProfileDisplayPreferencesTest extends TestCase
{
    public function test_malformed_widgets_input_fails_validation_instead_of_crashing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.display'), ['widgets' => [['nested']]])
            ->assertSessionHasErrors('widgets.0');
    }
}
