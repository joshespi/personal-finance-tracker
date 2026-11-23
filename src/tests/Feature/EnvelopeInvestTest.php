<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class EnvelopeInvestTest extends TestCase
{
    private function payload(Portfolio $portfolio, CashAccount $account, array $over = []): array
    {
        return array_merge([
            'symbol'          => 'VOO',
            'asset_type'      => 'stock',
            'quantity'        => 2,
            'price_per_unit'  => 100,
            'fees'            => 1,
            'transacted_at'   => '2026-11-01',
            'portfolio_id'    => $portfolio->id,
            'cash_account_id' => $account->id,
        ], $over);
    }

    private function setUpFunded(): array
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $envelope  = Envelope::factory()->for($user)->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 1000]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 500]);

        return [$user, $account, $envelope, $portfolio];
    }

    public function test_invest_creates_buy_and_envelope_tagged_withdrawal(): void
    {
        [$user, $account, $envelope, $portfolio] = $this->setUpFunded();

        $this->actingAs($user)
            ->post(route('envelopes.invest', $envelope), $this->payload($portfolio, $account))
            ->assertRedirect(route('envelopes.show', $envelope));

        $buy = Transaction::firstOrFail();
        $this->assertSame($envelope->id, $buy->envelope_id);
        $this->assertSame($portfolio->id, $buy->portfolio_id);

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'envelope_id'     => $envelope->id,
            'type'            => 'withdrawal',
            'amount'          => 201,
        ]);

        $this->assertEqualsWithDelta(299.0, $envelope->fresh()->balance(), 0.001);
        // Cash and envelope drop together, so ready-to-assign is unchanged (1000 - 500).
        $this->assertEqualsWithDelta(500.0, $user->readyToAssign(), 0.001);
    }

    public function test_invest_404s_when_investing_feature_is_off(): void
    {
        [$user, $account, $envelope, $portfolio] = $this->setUpFunded();
        $user->update(['features' => ['investing' => false]]);

        $this->actingAs($user)
            ->post(route('envelopes.invest', $envelope), $this->payload($portfolio, $account))
            ->assertNotFound();

        $this->assertSame(0, Transaction::count());
    }

    public function test_show_displays_invested_value_at_latest_price(): void
    {
        [$user, $account, $envelope, $portfolio] = $this->setUpFunded();

        $this->actingAs($user)
            ->post(route('envelopes.invest', $envelope), $this->payload($portfolio, $account));

        AssetPrice::create([
            'asset_id' => Asset::firstOrFail()->id, 'price' => 150, 'currency' => 'USD', 'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('envelopes.show', $envelope))
            ->assertOk()
            ->assertSee('Invested')
            ->assertSee('300.00');
    }

    public function test_cannot_use_another_users_portfolio_or_account(): void
    {
        [$user, $account, $envelope, $portfolio] = $this->setUpFunded();
        $otherPortfolio                          = Portfolio::factory()->create();
        $otherAccount                            = CashAccount::factory()->create();

        $this->actingAs($user)
            ->post(route('envelopes.invest', $envelope), $this->payload($otherPortfolio, $account))
            ->assertSessionHasErrors('portfolio_id');
        $this->actingAs($user)
            ->post(route('envelopes.invest', $envelope), $this->payload($portfolio, $otherAccount))
            ->assertSessionHasErrors('cash_account_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_cannot_invest_from_another_users_envelope(): void
    {
        [, $account, , $portfolio] = $this->setUpFunded();
        $stranger                  = User::factory()->create();
        $envelope                  = Envelope::factory()->create();

        $this->actingAs($stranger)
            ->post(route('envelopes.invest', $envelope), $this->payload($portfolio, $account))
            ->assertForbidden();
    }
}
