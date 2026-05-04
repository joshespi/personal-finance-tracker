<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\ManualAsset;
use App\Models\ManualValuation;
use App\Models\Portfolio;
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

        $this->seedPortfolio($demo);
        $this->seedManualAssetsAndLiabilities($demo);
        $this->seedCashAccounts($demo);
        $this->seedEnvelopes($demo);
    }

    private function seedPortfolio(User $user): void
    {
        $portfolio = Portfolio::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Long Term'],
            ['currency' => 'USD'],
        );

        $assets = [
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.',            'type' => 'stock',        'price' => 185.00],
            ['symbol' => 'MSFT', 'name' => 'Microsoft Corp.',       'type' => 'stock',        'price' => 415.00],
            ['symbol' => 'VOO',  'name' => 'Vanguard S&P 500',      'type' => 'stock',        'price' => 530.00],
            ['symbol' => 'BTC',  'name' => 'Bitcoin',               'type' => 'crypto',       'price' => 68000.00],
            ['symbol' => 'VNQ',  'name' => 'Vanguard Real Estate',  'type' => 'real_estate',  'price' => 85.00],
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

            // Three buys, spread across the last year, cheaper further back.
            for ($n = 0; $n < 3; $n++) {
                $date  = $today->subMonths(($n * 4) + $i)->toDateString();
                $price = round($row['price'] * (0.7 + ($n * 0.1)), 2);
                $qty   = $row['type'] === 'crypto' ? 0.05 : 5;

                Transaction::firstOrCreate(
                    [
                        'portfolio_id'  => $portfolio->id,
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
                'asset_class' => 'real_estate',
                'cost_basis'  => 320000,
                'currency'    => 'USD',
            ],
        );

        ManualValuation::updateOrCreate(
            ['manual_asset_id' => $home->id, 'valued_at' => CarbonImmutable::now()->subYear()->toDateString()],
            ['value' => 380000, 'notes' => 'Purchase appraisal'],
        );
        ManualValuation::updateOrCreate(
            ['manual_asset_id' => $home->id, 'valued_at' => CarbonImmutable::now()->toDateString()],
            ['value' => 410000, 'notes' => 'Recent estimate'],
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

        // Six months of paycheck deposits + everyday spending in checking.
        for ($month = 5; $month >= 0; $month--) {
            $date = CarbonImmutable::now()->subMonths($month)->startOfMonth();

            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(0)->toDateString(), 'description' => 'Paycheck'],
                ['type' => 'deposit', 'amount' => 4200],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(14)->toDateString(), 'description' => 'Paycheck'],
                ['type' => 'deposit', 'amount' => 4200],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(2)->toDateString(), 'description' => 'Rent'],
                ['type' => 'withdrawal', 'amount' => 1850],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $checking->id, 'occurred_at' => $date->addDays(20)->toDateString(), 'description' => 'Groceries + dining'],
                ['type' => 'withdrawal', 'amount' => 950],
            );
            CashTransaction::firstOrCreate(
                ['cash_account_id' => $savings->id, 'occurred_at' => $date->addDays(15)->toDateString(), 'description' => 'Transfer to savings'],
                ['type' => 'deposit', 'amount' => 1000],
            );
        }
    }

    private function seedEnvelopes(User $user): void
    {
        $envelopes = [
            ['name' => 'Groceries', 'monthly_target' => 600, 'color' => '#10b981', 'sort_order' => 1, 'spend' => 540],
            ['name' => 'Dining',    'monthly_target' => 300, 'color' => '#f59e0b', 'sort_order' => 2, 'spend' => 240],
            ['name' => 'Travel',    'monthly_target' => 200, 'color' => '#3b82f6', 'sort_order' => 3, 'spend' => 100],
        ];

        foreach ($envelopes as $row) {
            $env = Envelope::firstOrCreate(
                ['user_id' => $user->id, 'name' => $row['name']],
                [
                    'monthly_target' => $row['monthly_target'],
                    'color'          => $row['color'],
                    'sort_order'     => $row['sort_order'],
                ],
            );

            for ($month = 2; $month >= 0; $month--) {
                $date = CarbonImmutable::now()->subMonths($month)->startOfMonth();

                EnvelopeTransaction::firstOrCreate(
                    ['envelope_id' => $env->id, 'occurred_at' => $date->toDateString(), 'type' => 'fund'],
                    ['amount' => $row['monthly_target'], 'description' => 'Monthly fund'],
                );
                EnvelopeTransaction::firstOrCreate(
                    ['envelope_id' => $env->id, 'occurred_at' => $date->addDays(15)->toDateString(), 'type' => 'spend'],
                    ['amount' => $row['spend'], 'description' => $row['name'].' spend'],
                );
            }
        }
    }
}
