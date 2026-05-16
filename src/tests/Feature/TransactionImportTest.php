<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\User;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    private function makeCsv(string $rows, string $header = "date,symbol,asset_type,type,quantity,price_per_unit,fees,currency,notes\n"): string
    {
        return $header . $rows;
    }

    private function csvFile(string $content): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tmp, $content);

        return ['csv_file' => new \Illuminate\Http\UploadedFile($tmp, 'import.csv', 'text/csv', null, true)];
    }

    public function test_template_requires_auth(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->get(route('portfolios.transactions.import.template', $portfolio))
            ->assertRedirect(route('login'));
    }

    public function test_template_returns_csv_download(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->get(route('portfolios.transactions.import.template', $portfolio))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('date,symbol,asset_type');
    }

    public function test_import_requires_auth(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->post(route('portfolios.transactions.import', $portfolio), $this->csvFile(''))
            ->assertRedirect(route('login'));
    }

    public function test_cannot_import_into_another_users_portfolio(): void
    {
        $portfolio = Portfolio::factory()->create();
        $other     = User::factory()->create();

        $csv = $this->makeCsv("2024-01-15,BTC,crypto,buy,0.5,40000,10,USD,\n");

        $this->actingAs($other)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertForbidden();
    }

    public function test_valid_csv_imports_transactions(): void
    {
        $portfolio = Portfolio::factory()->create();

        $csv = $this->makeCsv(
            "2024-01-15,BTC,crypto,buy,0.5,40000,10,USD,initial purchase\n"
            . "2024-03-20,AAPL,stock,buy,10,180.50,1.99,USD,\n"
        );

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertRedirect(route('portfolios.transactions.index', $portfolio))
            ->assertSessionHas('success', '2 transaction(s) imported successfully.');

        $this->assertDatabaseHas('assets', ['symbol' => 'BTC', 'asset_type' => 'crypto']);
        $this->assertDatabaseHas('assets', ['symbol' => 'AAPL', 'asset_type' => 'stock']);
        $this->assertDatabaseHas('transactions', [
            'portfolio_id'   => $portfolio->id,
            'type'           => 'buy',
            'quantity'       => 0.5,
            'price_per_unit' => 40000,
        ]);
    }

    public function test_import_reuses_existing_asset(): void
    {
        $portfolio = Portfolio::factory()->create();
        $existing  = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        $csv = $this->makeCsv("2024-01-15,AAPL,stock,buy,5,180,0,USD,\n");

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertRedirect();

        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseHas('transactions', ['asset_id' => $existing->id, 'portfolio_id' => $portfolio->id]);
    }

    public function test_invalid_asset_type_accumulates_error(): void
    {
        $portfolio = Portfolio::factory()->create();

        $csv = $this->makeCsv("2024-01-15,BTC,commodity,buy,1,40000,0,USD,\n");

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertRedirect()
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_invalid_transaction_type_accumulates_error(): void
    {
        $portfolio = Portfolio::factory()->create();

        $csv = $this->makeCsv("2024-01-15,BTC,crypto,hodl,1,40000,0,USD,\n");

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertSessionHasErrors('csv_file');
    }

    public function test_bad_date_format_accumulates_error(): void
    {
        $portfolio = Portfolio::factory()->create();

        $csv = $this->makeCsv("15/01/2024,BTC,crypto,buy,1,40000,0,USD,\n");

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertSessionHasErrors('csv_file');
    }

    public function test_missing_csv_file_fails_validation(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), [])
            ->assertSessionHasErrors('csv_file');
    }

    public function test_empty_csv_body_returns_error(): void
    {
        $portfolio = Portfolio::factory()->create();

        $csv = $this->makeCsv(''); // header only, no data rows

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_invalid_header_returns_error(): void
    {
        $portfolio = Portfolio::factory()->create();

        $csv = $this->makeCsv("2024-01-15,BTC\n", "date,symbol\n"); // too few header columns

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertSessionHasErrors('csv_file');
    }

    public function test_blank_rows_are_skipped(): void
    {
        $portfolio = Portfolio::factory()->create();

        $csv = $this->makeCsv(
            "2024-01-15,BTC,crypto,buy,0.5,40000,0,USD,\n"
            . "\n"
            . "\n"
        );

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertRedirect()
            ->assertSessionHas('success', '1 transaction(s) imported successfully.');
    }

    public function test_all_supported_transaction_types_accepted(): void
    {
        $portfolio = Portfolio::factory()->create();

        $types = ['buy', 'sell', 'dividend', 'staking_reward', 'transfer_in', 'transfer_out'];
        $rows  = '';
        foreach ($types as $i => $type) {
            $rows .= "2024-0" . ($i + 1) . "-15,BTC,crypto,{$type},0.1,40000,0,USD,\n";
        }

        $csv = $this->makeCsv($rows);

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.import', $portfolio), $this->csvFile($csv))
            ->assertRedirect()
            ->assertSessionHas('success', count($types) . ' transaction(s) imported successfully.');
    }
}
