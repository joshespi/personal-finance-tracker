<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\CashAccount;
use App\Models\Envelope;
use App\Models\Liability;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DevSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevSeederTest extends TestCase
{
    public function test_seeder_creates_demo_and_admin_users_with_password(): void
    {
        $this->seed(DevSeeder::class);

        $demo = User::where('email', 'demo@example.com')->first();
        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($demo, 'demo user missing');
        $this->assertNotNull($admin, 'admin user missing');
        $this->assertTrue(Hash::check('password', $demo->password));
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertFalse((bool) $demo->is_admin);
    }

    public function test_seeder_creates_full_demo_dataset_for_demo_user(): void
    {
        $this->seed(DevSeeder::class);

        $demo = User::where('email', 'demo@example.com')->firstOrFail();

        $this->assertSame(1, Portfolio::where('user_id', $demo->id)->count());
        $this->assertGreaterThanOrEqual(4, Asset::count());
        $this->assertGreaterThanOrEqual(12, Transaction::count());
        $this->assertSame(1, ManualAsset::count());
        $this->assertSame(2, Liability::where('user_id', $demo->id)->count());
        $this->assertSame(2, CashAccount::where('user_id', $demo->id)->count());
        $this->assertSame(3, Envelope::where('user_id', $demo->id)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DevSeeder::class);
        $first = [
            'users'    => User::count(),
            'tx'       => Transaction::count(),
            'cash_tx'  => \App\Models\CashTransaction::count(),
            'env_tx'   => \App\Models\EnvelopeTransaction::count(),
        ];

        $this->seed(DevSeeder::class);
        $second = [
            'users'    => User::count(),
            'tx'       => Transaction::count(),
            'cash_tx'  => \App\Models\CashTransaction::count(),
            'env_tx'   => \App\Models\EnvelopeTransaction::count(),
        ];

        $this->assertSame($first, $second, 'Seeder should not duplicate rows on re-run');
    }
}
