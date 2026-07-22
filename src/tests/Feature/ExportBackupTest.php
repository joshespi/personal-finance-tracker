<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\Pension;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class ExportBackupTest extends TestCase
{
    public function test_export_index_requires_auth(): void
    {
        $this->get(route('export.index'))->assertRedirect(route('login'));
    }

    public function test_backup_requires_auth(): void
    {
        $this->get(route('export.backup'))->assertRedirect(route('login'));
    }

    public function test_backup_returns_json_with_correct_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('export.backup'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->streamedContent(), true);

        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('portfolios', $data);
        $this->assertArrayHasKey('liabilities', $data);
        $this->assertArrayHasKey('cash_accounts', $data);
        $this->assertArrayHasKey('pensions', $data);
        $this->assertArrayHasKey('envelopes', $data);
        $this->assertArrayHasKey('scheduled_transactions', $data);
        $this->assertSame(1, $data['version']);
    }

    public function test_backup_includes_portfolio_transactions(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create(['name' => 'My Brokerage']);
        $asset     = Asset::factory()->stock()->create(['symbol' => 'NVDA']);

        Transaction::factory()->for($portfolio)->create([
            'asset_id'       => $asset->id,
            'type'           => 'buy',
            'quantity'       => 5,
            'price_per_unit' => 800.00,
            'transacted_at'  => '2025-03-01 00:00:00',
        ]);

        $data = json_decode(
            $this->actingAs($user)->get(route('export.backup'))->streamedContent(),
            true
        );

        $this->assertCount(1, $data['portfolios']);
        $this->assertSame('My Brokerage', $data['portfolios'][0]['name']);
        $this->assertCount(1, $data['portfolios'][0]['transactions']);
        $this->assertSame('NVDA', $data['portfolios'][0]['transactions'][0]['symbol']);
        $this->assertEquals(5.0, $data['portfolios'][0]['transactions'][0]['quantity']);
    }

    public function test_backup_includes_liabilities_with_balances(): void
    {
        $user      = User::factory()->create();
        $liability = Liability::factory()->for($user)->create(['name' => 'Car Loan']);
        LiabilityBalance::factory()->for($liability)->create(['balance' => 12000]);

        $data = json_decode(
            $this->actingAs($user)->get(route('export.backup'))->streamedContent(),
            true
        );

        $this->assertCount(1, $data['liabilities']);
        $this->assertSame('Car Loan', $data['liabilities'][0]['name']);
        $this->assertCount(1, $data['liabilities'][0]['balances']);
        $this->assertEquals(12000.0, $data['liabilities'][0]['balances'][0]['balance']);
    }

    public function test_backup_includes_cash_account_transactions(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create(['name' => 'Main Checking']);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create([
            'amount'      => 500,
            'description' => 'Paycheck',
        ]);

        $data = json_decode(
            $this->actingAs($user)->get(route('export.backup'))->streamedContent(),
            true
        );

        $this->assertCount(1, $data['cash_accounts']);
        $this->assertSame('Main Checking', $data['cash_accounts'][0]['name']);
        $this->assertCount(1, $data['cash_accounts'][0]['transactions']);
        $this->assertSame('deposit', $data['cash_accounts'][0]['transactions'][0]['type']);
        $this->assertEquals(500.0, $data['cash_accounts'][0]['transactions'][0]['amount']);
    }

    public function test_backup_includes_pensions(): void
    {
        $user = User::factory()->create();
        Pension::factory()->for($user)->create([
            'name'                 => 'URS Pension',
            'service_credit_years' => 16.588,
            'multiplier_pct'       => 2.000,
            'include_in_net_worth' => true,
        ]);

        $data = json_decode(
            $this->actingAs($user)->get(route('export.backup'))->streamedContent(),
            true
        );

        $this->assertCount(1, $data['pensions']);
        $this->assertSame('URS Pension', $data['pensions'][0]['name']);
        $this->assertEqualsWithDelta(16.588, $data['pensions'][0]['service_credit_years'], 0.001);
        $this->assertTrue($data['pensions'][0]['include_in_net_worth']);
    }

    public function test_backup_includes_envelope_transactions(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Groceries', 'is_mandatory' => true]);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create([
            'amount'      => 75.50,
            'description' => 'Weekly shop',
        ]);

        $data = json_decode(
            $this->actingAs($user)->get(route('export.backup'))->streamedContent(),
            true
        );

        $this->assertCount(1, $data['envelopes']);
        $this->assertSame('Groceries', $data['envelopes'][0]['name']);
        $this->assertTrue($data['envelopes'][0]['is_mandatory']);
        $this->assertCount(1, $data['envelopes'][0]['transactions']);
        $this->assertSame('spend', $data['envelopes'][0]['transactions'][0]['type']);
        $this->assertEquals(75.50, $data['envelopes'][0]['transactions'][0]['amount']);
    }

    public function test_backup_cross_user_isolation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Portfolio::factory()->for($userB)->create(['name' => 'Other Portfolio']);

        $data = json_decode(
            $this->actingAs($userA)->get(route('export.backup'))->streamedContent(),
            true
        );

        $this->assertCount(0, $data['portfolios']);
    }
}
