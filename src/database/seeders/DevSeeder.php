<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\IncomeEntry;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\ManualAsset;
use App\Models\ManualValuation;
use App\Models\Portfolio;
use App\Models\ScheduledTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name'              => 'Demo User',
                'password'          => 'password',
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'              => 'Admin User',
                'password'          => 'password',
                'email_verified_at' => now(),
                'is_admin'          => true,
            ],
        );

        $this->seedPortfolios($demo);
        $this->seedManualAssetsAndLiabilities($demo);
        $this->seedCashAccounts($demo);
        $this->seedEnvelopes($demo);
        $this->seedIncome($demo);
        $this->seedScheduledTransactions($demo);
    }

    private function seedPortfolios(User $user): void
    {
        $taxable = Portfolio::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Long Term'],
            ['currency' => 'USD', 'is_tax_advantaged' => false],
        );

        $ira = Portfolio::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Roth IRA'],
            ['currency' => 'USD', 'is_tax_advantaged' => true],
        );

        $assets = [
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.',            'type' => 'stock',        'price' => 185.00, 'portfolio' => $taxable],
            ['symbol' => 'MSFT', 'name' => 'Microsoft Corp.',       'type' => 'stock',        'price' => 415.00, 'portfolio' => $taxable],
            ['symbol' => 'VOO',  'name' => 'Vanguard S&P 500',      'type' => 'stock',        'price' => 530.00, 'portfolio' => $ira],
            ['symbol' => 'BTC',  'name' => 'Bitcoin',               'type' => 'crypto',       'price' => 68000.00, 'portfolio' => $taxable],
            ['symbol' => 'VNQ',  'name' => 'Vanguard Real Estate',  'type' => 'real_estate',  'price' => 85.00,  'portfolio' => $taxable],
            ['symbol' => 'BND',  'name' => 'Vanguard Total Bond',   'type' => 'bond',         'price' => 72.50,  'portfolio' => $ira],
        ];

        $today = CarbonImmutable::now();

        foreach ($assets as $i => $row) {
            $asset = Asset::firstOrCreate(
                ['symbol' => $row['symbol']],
                ['name' => $row['name'], 'asset_type' => $row['type']],
            );

            AssetPrice::updateOrCreate(
                ['asset_id' => $asset->id, 'recorded_at' => $today->toDateTimeString()],
                ['price' => $row['price'], 'currency' => 'USD'],
            );

            for ($n = 0; $n < 3; $n++) {
                $date  = $today->subMonths(($n * 4) + $i)->startOfDay()->toDateTimeString();
                $price = round($row['price'] * (0.7 + ($n * 0.1)), 2);
                $qty   = $row['type'] === 'crypto' ? 0.05 : 5;

                Transaction::firstOrCreate(
                    [
                        'portfolio_id'  => $row['portfolio']->id,
                        'asset_id'      => $asset->id,
                        'transacted_at' => $date,
                        'type'          => 'buy',
                    ],
                    [
                        'quantity'       => $qty,
                        'price_per_unit' => $price,
                        'fees'           => 0,
                        'currency'       => 'USD',
                    ],
                );
            }
        }
    }

    private function seedManualAssetsAndLiabilities(User $user): void
    {
        $portfolio = Portfolio::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Long Term'],
            ['currency' => 'USD'],
        );

        $home = ManualAsset::firstOrCreate(
            ['portfolio_id' => $portfolio->id, 'name' => 'Primary Residence'],
            [
                'asset_class'     => 'real_estate',
                'cost_basis'      => 320000,
                'currency'        => 'USD',
                'tracking_method' => 'static',
            ],
        );

        ManualValuation::updateOrCreate(
            ['manual_asset_id' => $home->id, 'valued_at' => CarbonImmutable::now()->subYear()->startOfDay()->toDateTimeString()],
            ['value' => 380000, 'notes' => 'Purchase appraisal'],
        );
        ManualValuation::updateOrCreate(
            ['manual_asset_id' => $home->id, 'valued_at' => CarbonImmutable::now()->startOfDay()->toDateTimeString()],
            ['value' => 410000, 'notes' => 'Recent estimate'],
        );

        // 401(k) auto-priced via VOO proxy ticker.
        $voo = Asset::firstOrCreate(
            ['symbol' => 'VOO'],
            ['name' => 'Vanguard S&P 500', 'asset_type' => 'stock'],
        );
        $anchorDate  = CarbonImmutable::now()->subMonths(6)->startOfDay();
        $anchorValue = 75000.0;
        $anchorPrice = 480.0;

        AssetPrice::updateOrCreate(
            ['asset_id' => $voo->id, 'recorded_at' => $anchorDate->toDateTimeString()],
            ['price' => $anchorPrice, 'currency' => 'USD'],
        );

        ManualAsset::updateOrCreate(
            ['portfolio_id' => $portfolio->id, 'name' => 'Employer 401(k)'],
            [
                'asset_class'             => 'stock',
                'cost_basis'              => 60000,
                'currency'                => 'USD',
                'tracking_method'         => 'proxy_ticker',
                'proxy_asset_id'          => $voo->id,
                'anchor_value'            => $anchorValue,
                'anchor_date'             => $anchorDate->toDateString(),
                'anchor_synthetic_shares' => round($anchorValue / $anchorPrice, 8),
            ],
        );

        $mortgage = Liability::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Mortgage'],
            [
                'manual_asset_id' => $home->id,
                'liability_type'  => 'mortgage',
                'interest_rate'   => 6.25,
                'currency'        => 'USD',
            ],
        );

        $balances = [
            [12, 256000],
            [6,  252000],
            [0,  248500],
        ];
        foreach ($balances as [$monthsAgo, $balance]) {
            LiabilityBalance::updateOrCreate(
                ['liability_id' => $mortgage->id, 'recorded_at' => CarbonImmutable::now()->subMonths($monthsAgo)->toDateTimeString()],
                ['balance' => $balance],
            );
        }

        $cc = Liability::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Visa Card'],
            [
                'liability_type' => 'credit_card',
                'interest_rate'  => 21.99,
                'currency'       => 'USD',
            ],
        );
        LiabilityBalance::updateOrCreate(
            ['liability_id' => $cc->id, 'recorded_at' => CarbonImmutable::now()->toDateTimeString()],
            ['balance' => 1450],
        );
    }

    private function seedCashAccounts(User $user): void
    {
        $checking = CashAccount::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Checking'],
            ['account_type' => 'checking', 'currency' => 'USD'],
        );

        $savings = CashAccount::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Savings'],
            ['account_type' => 'savings', 'currency' => 'USD'],
        );

        for ($month = 5; $month >= 0; $month--) {
            $date = CarbonImmutable::now()->subMonths($month)->startOfMonth();

            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(0)->startOfDay()->toDateTimeString(), 'description' => 'Paycheck'],
                ['type' => 'deposit', 'amount' => 4200],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(14)->startOfDay()->toDateTimeString(), 'description' => 'Paycheck'],
                ['type' => 'deposit', 'amount' => 4200],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(2)->startOfDay()->toDateTimeString(), 'description' => 'Rent'],
                ['type' => 'withdrawal', 'amount' => 1850],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(20)->startOfDay()->toDateTimeString(), 'description' => 'Groceries + dining'],
                ['type' => 'withdrawal', 'amount' => 950],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $savings->id, 'occurred_at' => $date->addDays(15)->startOfDay()->toDateTimeString(), 'description' => 'Transfer to savings'],
                ['type' => 'deposit', 'amount' => 1000],
            );
        }
    }

    private function seedEnvelopes(User $user): void
    {
        $envelopes = [
            ['name' => 'Rent',         'monthly_target' => 1850, 'color' => '#ef4444', 'sort_order' => 0, 'spend' => 1850, 'is_mandatory' => true,  'is_emergency_fund' => false],
            ['name' => 'Groceries',    'monthly_target' => 600,  'color' => '#10b981', 'sort_order' => 1, 'spend' => 540,  'is_mandatory' => true,  'is_emergency_fund' => false],
            ['name' => 'Utilities',    'monthly_target' => 220,  'color' => '#0ea5e9', 'sort_order' => 2, 'spend' => 195,  'is_mandatory' => true,  'is_emergency_fund' => false],
            ['name' => 'Dining',       'monthly_target' => 300,  'color' => '#f59e0b', 'sort_order' => 3, 'spend' => 240,  'is_mandatory' => false, 'is_emergency_fund' => false],
            ['name' => 'Travel',       'monthly_target' => 200,  'color' => '#3b82f6', 'sort_order' => 4, 'spend' => 100,  'is_mandatory' => false, 'is_emergency_fund' => false],
            ['name' => 'Emergency Fund', 'monthly_target' => 500, 'color' => '#6366f1', 'sort_order' => 5, 'spend' => 0,   'is_mandatory' => false, 'is_emergency_fund' => true],
        ];

        foreach ($envelopes as $row) {
            $env = Envelope::firstOrCreate(
                ['user_id' => $user->id, 'name' => $row['name']],
                [
                    'monthly_target'    => $row['monthly_target'],
                    'color'             => $row['color'],
                    'sort_order'        => $row['sort_order'],
                    'is_mandatory'      => $row['is_mandatory'],
                    'is_emergency_fund' => $row['is_emergency_fund'],
                ],
            );

            for ($month = 2; $month >= 0; $month--) {
                $date = CarbonImmutable::now()->subMonths($month)->startOfMonth();

                EnvelopeTransaction::firstOrCreate(
                    ['envelope_id' => $env->id, 'occurred_at' => $date->startOfDay()->toDateTimeString(), 'type' => 'fund'],
                    ['amount' => $row['monthly_target'], 'description' => 'Monthly fund'],
                );

                if ($row['spend'] > 0) {
                    EnvelopeTransaction::firstOrCreate(
                        ['envelope_id' => $env->id, 'occurred_at' => $date->addDays(15)->startOfDay()->toDateTimeString(), 'type' => 'spend'],
                        ['amount' => $row['spend'], 'description' => $row['name'] . ' spend'],
                    );
                }
            }
        }

        // Savings-goal envelope.
        Envelope::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Vacation Fund'],
            [
                'color'       => '#8b5cf6',
                'sort_order'  => 6,
                'goal_amount' => 5000,
                'goal_date'   => CarbonImmutable::now()->addYear()->startOfMonth()->toDateString(),
            ],
        );
    }

    private function seedIncome(User $user): void
    {
        for ($month = 5; $month >= 0; $month--) {
            $date = CarbonImmutable::now()->subMonths($month)->startOfMonth();

            IncomeEntry::firstOrCreate(
                ['user_id' => $user->id, 'occurred_at' => $date->toDateString(), 'description' => 'Paycheck'],
                ['amount' => 4200],
            );
            IncomeEntry::firstOrCreate(
                ['user_id' => $user->id, 'occurred_at' => $date->addDays(14)->toDateString(), 'description' => 'Paycheck'],
                ['amount' => 4200],
            );
        }
    }

    private function seedScheduledTransactions(User $user): void
    {
        $checking = CashAccount::where('user_id', $user->id)->where('name', 'Checking')->first();
        $rent     = Envelope::where('user_id', $user->id)->where('name', 'Rent')->first();

        if ($checking) {
            ScheduledTransaction::firstOrCreate(
                ['user_id' => $user->id, 'description' => 'Paycheck', 'type' => 'cash_deposit', 'cash_account_id' => $checking->id],
                ['amount' => 4200, 'recurrence' => 'biweekly', 'next_due_at' => CarbonImmutable::now()->addDays(7)->toDateString(), 'is_active' => true],
            );
        }

        if ($rent) {
            ScheduledTransaction::firstOrCreate(
                ['user_id' => $user->id, 'description' => 'Rent', 'type' => 'envelope_spend', 'envelope_id' => $rent->id],
                ['amount' => 1850, 'recurrence' => 'monthly', 'next_due_at' => CarbonImmutable::now()->addMonth()->startOfMonth()->toDateString(), 'is_active' => true],
            );
        }
    }
}
