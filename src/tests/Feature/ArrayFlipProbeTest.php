<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ArrayFlipProbeTest extends TestCase
{
    public function test_malformed_widgets_input(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('profile.display'), ['widgets' => [['nested']]]);

        dump($response->status());
        dump(substr($response->getContent() ?? '', 0, 500));
    }
}
