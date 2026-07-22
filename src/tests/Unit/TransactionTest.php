<?php

namespace Tests\Unit;

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
}
