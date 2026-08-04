<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class TransactionMoveAccountTest extends TestCase
{
    public function test_transaction_can_be_moved_to_another_owned_portfolio(): void
    {
        $user        = User::factory()->create();
        $asset       = Asset::factory()->stock()->create();
        $source      = Portfolio::factory()->for($user)->create();
        $destination = Portfolio::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($source)->for($asset)->buy()->create();

        $this->actingAs($user)
            ->put(route('transactions.update', $transaction), [
                'portfolio_id'   => $destination->id,
                'type'           => 'buy',
                'quantity'       => (float) $transaction->quantity,
                'price_per_unit' => (float) $transaction->price_per_unit,
                'currency'       => $transaction->currency,
                'transacted_at'  => $transaction->transacted_at->format('Y-m-d'),
            ])
            ->assertRedirect(route('portfolios.transactions.index', $destination));

        $this->assertSame($destination->id, $transaction->fresh()->portfolio_id);
    }

    public function test_transaction_cannot_be_moved_to_another_users_portfolio(): void
    {
        $user        = User::factory()->create();
        $other       = User::factory()->create();
        $asset       = Asset::factory()->stock()->create();
        $source      = Portfolio::factory()->for($user)->create();
        $foreign     = Portfolio::factory()->for($other)->create();
        $transaction = Transaction::factory()->for($source)->for($asset)->buy()->create();

        $this->actingAs($user)
            ->put(route('transactions.update', $transaction), [
                'portfolio_id'   => $foreign->id,
                'type'           => 'buy',
                'quantity'       => (float) $transaction->quantity,
                'price_per_unit' => (float) $transaction->price_per_unit,
                'currency'       => $transaction->currency,
                'transacted_at'  => $transaction->transacted_at->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('portfolio_id');

        $this->assertSame($source->id, $transaction->fresh()->portfolio_id);
    }

    public function test_transfer_transaction_cannot_be_moved_to_another_account(): void
    {
        $user           = User::factory()->create();
        $asset          = Asset::factory()->stock()->create();
        $source         = Portfolio::factory()->for($user)->create();
        $destination    = Portfolio::factory()->for($user)->create();
        $otherPortfolio = Portfolio::factory()->for($user)->create();
        $transferOut    = Transaction::factory()->for($source)->for($asset)->create(['type' => 'transfer_out']);
        Transaction::factory()->for($destination)->for($asset)->create([
            'type'               => 'transfer_in',
            'linked_transfer_id' => $transferOut->id,
        ]);

        $this->actingAs($user)
            ->put(route('transactions.update', $transferOut), [
                'portfolio_id'   => $otherPortfolio->id,
                'type'           => 'transfer_out',
                'quantity'       => (float) $transferOut->quantity,
                'price_per_unit' => (float) $transferOut->price_per_unit,
                'currency'       => $transferOut->currency,
                'transacted_at'  => $transferOut->transacted_at->format('Y-m-d'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame($source->id, $transferOut->fresh()->portfolio_id);
    }
}
