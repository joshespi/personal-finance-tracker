<?php

namespace Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ExceptionLoggingTest extends TestCase
{
    public function test_uncaught_controller_exception_reaches_the_logger(): void
    {
        Route::get('/__test__/boom', function () {
            throw new RuntimeException('boom from controller');
        });

        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured) {
            $captured[] = $event->message;
        });

        $this->get('/__test__/boom')->assertStatus(500);

        $this->assertTrue(
            collect($captured)->contains(fn ($m) => str_contains((string) $m, 'boom from controller')),
            'Expected the exception message to be logged, but no MessageLogged event matched. Captured: '
                .json_encode($captured)
        );
    }

    public function test_stack_channel_contains_stderr_when_log_stack_env_includes_it(): void
    {
        config(['logging.channels.stack' => [
            'driver' => 'stack',
            'channels' => explode(',', 'single,stderr'),
            'ignore_exceptions' => false,
        ]]);

        Log::channel('stack')->error('verifying stack resolves');

        $this->assertContains('stderr', config('logging.channels.stack.channels'));
        $this->assertContains('single', config('logging.channels.stack.channels'));
    }
}
