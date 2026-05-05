<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Regression guard — if this fails, RefreshDatabase will wipe dev data on next run.
class TestDatabaseIsolationTest extends TestCase
{
    public function test_test_suite_runs_against_sqlite_in_memory(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }
}
