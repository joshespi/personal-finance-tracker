<?php

namespace Tests\Unit;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    public function test_total_cost_includes_cash_fee(): void
    {
        $t = Transaction::factory()->make(['quantity' => 2.0, 'price_per_unit' => 100.0, 'fees' => 5.0, 'fee_in_asset' => false]);

        $this->assertEquals(205.0, $t->totalCost());
    }

    public function test_total_cost_excludes_fee_paid_in_asset(): void
    {
        // Regression: totalCost() used to add `fees` unconditionally, overstating cost
        // for fee-in-asset transactions (the fee already came out in asset units, not cash).
        $t = Transaction::factory()->make(['quantity' => 2.0, 'price_per_unit' => 100.0, 'fees' => 5.0, 'fee_in_asset' => true]);

        $this->assertEquals(200.0, $t->totalCost());
    }

    public function test_usd_fee(): void
    {
        $cash  = Transaction::factory()->make(['fees' => 5.0, 'fee_in_asset' => false]);
        $asset = Transaction::factory()->make(['fees' => 5.0, 'fee_in_asset' => true]);

        $this->assertEquals(5.0, $cash->usdFee());
        $this->assertEquals(0.0, $asset->usdFee());
    }

    public function test_quantity_with_asset_fee(): void
    {
        $cash  = Transaction::factory()->make(['quantity' => 1.0, 'fees' => 0.001, 'fee_in_asset' => false]);
        $asset = Transaction::factory()->make(['quantity' => 1.0, 'fees' => 0.001, 'fee_in_asset' => true]);

        $this->assertEquals(1.0, $cash->quantityWithAssetFee());
        $this->assertEquals(1.001, $asset->quantityWithAssetFee());
    }

    public function test_net_of_fee(): void
    {
        $this->assertEquals(0.999, Transaction::netOfFee(1.0, 0.001, true));
        $this->assertEquals(1.0, Transaction::netOfFee(1.0, 0.001, false));
        $this->assertEquals(0.0, Transaction::netOfFee(0.5, 1.0, true));
    }

    /**
     * Regression: the three call sites that spread a cash fee over units each used to
     * divide by max(1, quantity) — a unit floor, not a zero guard — silently shrinking
     * the fee on any sub-1-unit trade (routine for crypto). The rule now lives here only.
     */
    public function test_usd_fee_per_unit_divides_by_actual_quantity(): void
    {
        $make = fn (array $attrs) => Transaction::factory()->make($attrs + [
            'type' => TransactionType::Buy, 'price_per_unit' => 100.0, 'transacted_at' => now(),
        ]);

        // 0.5 units, $10 fee => $20/unit, not $10 (which max(1, qty) would have given).
        $this->assertEquals(20.0, $make(['quantity' => 0.5, 'fees' => 10.0, 'fee_in_asset' => false])->usdFeePerUnit());
        $this->assertEquals(5.0, $make(['quantity' => 2.0, 'fees' => 10.0, 'fee_in_asset' => false])->usdFeePerUnit());
        // An in-asset fee is not cash, and a zero quantity must not divide by zero.
        $this->assertEquals(0.0, $make(['quantity' => 0.5, 'fees' => 10.0, 'fee_in_asset' => true])->usdFeePerUnit());
        $this->assertEquals(0.0, $make(['quantity' => 0.0, 'fees' => 10.0, 'fee_in_asset' => false])->usdFeePerUnit());
    }

    /**
     * The same fractional-fee rule as it reaches cost basis: 0.5 units at $100/unit with a
     * $10 cash fee costs $60 ($50 + the full $10), not $55.
     */
    public function test_accumulate_fifo_cost_basis_applies_full_fee_on_fractional_quantity(): void
    {
        $buy = Transaction::factory()->make([
            'type' => TransactionType::Buy, 'quantity' => 0.5, 'price_per_unit' => 100.0,
            'fees' => 10.0, 'fee_in_asset' => false, 'transacted_at' => now(),
        ]);

        [$qty, $cost] = Transaction::accumulateFifoCostBasis(collect([$buy]));

        $this->assertEquals(0.5, $qty);
        $this->assertEquals(60.0, $cost);
    }
}
