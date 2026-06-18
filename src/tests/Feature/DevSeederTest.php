<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\IncomeEntry;
use App\Models\Liability;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use App\Models\ScheduledTransaction;
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

        $demo  = User::where('email', 'demo@example.com')->first();
        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($demo, 'demo user missing');
        $this->assertNotNull($admin, 'admin user missing');
        $this->assertTrue(Hash::check('password', $demo->password));
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertTrue((bool) $demo->is_admin);
    }

    public function test_seeder_creates_full_demo_dataset_for_demo_user(): void
    {
        $this->seed(DevSeeder::class);

        $demo = User::where('email', 'demo@example.com')->firstOrFail();

        $this->assertSame(2, Portfolio::where('user_id', $demo->id)->count());
        $this->assertTrue(Portfolio::where('user_id', $demo->id)->where('is_tax_advantaged', true)->exists());
        $this->assertGreaterThanOrEqual(5, Asset::count());
        $this->assertTrue(Asset::where('asset_type', 'bond')->exists());
        $this->assertGreaterThanOrEqual(12, Transaction::count());
        $this->assertSame(2, ManualAsset::count());
        $this->assertTrue(ManualAsset::where('tracking_method', 'proxy_ticker')->exists());
        $this->assertSame(2, Liability::where('user_id', $demo->id)->count());
        $this->assertSame(2, CashAccount::where('user_id', $demo->id)->count());
        $this->assertSame(7, Envelope::where('user_id', $demo->id)->count());
        $this->assertTrue(Envelope::where('user_id', $demo->id)->where('is_emergency_fund', true)->exists());
        $this->assertTrue(Envelope::where('user_id', $demo->id)->whereNotNull('goal_amount')->exists());
        $this->assertGreaterThanOrEqual(12, IncomeEntry::where('user_id', $demo->id)->count());
        $this->assertGreaterThanOrEqual(2, ScheduledTransaction::where('user_id', $demo->id)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DevSeeder::class);
        $first = [
            'users'   => User::count(),
            'tx'      => Transaction::count(),
            'cash_tx' => CashTransaction::count(),
            'env_tx'  => EnvelopeTransaction::count(),
        ];

        $this->seed(DevSeeder::class);
        $second = [
            'users'   => User::count(),
            'tx'      => Transaction::count(),
            'cash_tx' => CashTransaction::count(),
            'env_tx'  => EnvelopeTransaction::count(),
        ];

        $this->assertSame($first, $second, 'Seeder should not duplicate rows on re-run');
    }
}
