<?php

namespace Tests;

use App\Services\DemoMode;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\View;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutVite();
        // Mirror ShareDemoMode middleware so Livewire component tests can render $demo-aware views.
        View::share('demo', app(DemoMode::class));
    }
}
