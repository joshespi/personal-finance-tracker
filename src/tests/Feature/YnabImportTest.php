<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YnabImportTest extends TestCase
{
    public function test_import_index_requires_auth(): void
    {
        $this->get(route('import.ynab'))->assertRedirect(route('login'));
    }

    public function test_upload_requires_auth(): void
    {
        $this->post(route('import.ynab.upload'))->assertRedirect(route('login'));
    }

    public function test_upload_parses_ynab_csv_and_redirects_to_preview(): void
    {
        $user = User::factory()->create();
        $csv  = $this->ynabCsv([
            ['Checking', '01/15/2025', 'Grocery Store', 'Food', '', '$45.00', ''],
            ['Checking', '01/16/2025', 'Paycheck', '', '', '', '$2,000.00'],
        ]);

        $this->actingAs($user)
            ->post(route('import.ynab.upload'), ['csv_file' => $csv])
            ->assertRedirect(route('import.ynab.preview'));
    }

    public function test_upload_rejects_empty_csv(): void
    {
        $user = User::factory()->create();
        $csv  = $this->ynabCsv([]);

        $this->actingAs($user)
            ->post(route('import.ynab.upload'), ['csv_file' => $csv])
            ->assertRedirect()
            ->assertSessionHasErrors('csv_file');
    }

    public function test_commit_creates_transactions_for_mapped_accounts(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create(['name' => 'My Checking']);

        $csv = $this->ynabCsv([
            ['Checking', '01/15/2025', 'Coffee', 'Dining', 'morning coffee', '$4.50', ''],
            ['Checking', '01/16/2025', 'Paycheck', '', '', '', '$3,000.00'],
        ]);

        // Upload
        $this->actingAs($user)->post(route('import.ynab.upload'), ['csv_file' => $csv]);

        // Commit: map "Checking" → existing account
        $this->actingAs($user)
            ->post(route('import.ynab.commit'), [
                'account_map' => ['Checking' => (string) $account->id],
            ])
            ->assertRedirect(route('cash-accounts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'withdrawal', 'amount' => 4.50, 'cash_account_id' => $account->id]);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'deposit',    'amount' => 3000.00, 'cash_account_id' => $account->id]);
    }

    public function test_commit_creates_new_account_when_map_value_is_new(): void
    {
        $user = User::factory()->create();

        $csv = $this->ynabCsv([
            ['Savings', '02/01/2025', 'Transfer', '', '', '', '$500.00'],
        ]);

        $this->actingAs($user)->post(route('import.ynab.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.ynab.commit'), [
                'account_map' => ['Savings' => 'new'],
            ])
            ->assertRedirect(route('cash-accounts.index'));

        $this->assertDatabaseHas('cash_accounts', ['name' => 'Savings', 'user_id' => $user->id]);
        $this->assertDatabaseCount('cash_transactions', 1);
    }

    public function test_commit_skips_accounts_mapped_to_skip(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        $csv = $this->ynabCsv([
            ['Checking', '01/01/2025', 'Coffee', '', '', '$5.00', ''],
            ['Credit',   '01/01/2025', 'Amazon', '', '', '$99.00', ''],
        ]);

        $this->actingAs($user)->post(route('import.ynab.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.ynab.commit'), [
                'account_map' => [
                    'Checking' => (string) $account->id,
                    'Credit'   => 'skip',
                ],
            ]);

        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseHas('cash_transactions', ['amount' => 5.00]);
    }

    public function test_cancel_clears_session_and_temp_file(): void
    {
        $user = User::factory()->create();
        $csv  = $this->ynabCsv([
            ['Checking', '01/01/2025', 'Test', '', '', '$1.00', ''],
        ]);

        $this->actingAs($user)->post(route('import.ynab.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.ynab.cancel'))
            ->assertRedirect(route('import.ynab'));

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_cross_user_isolation(): void
    {
        $userA   = User::factory()->create();
        $userB   = User::factory()->create();
        $account = CashAccount::factory()->for($userB)->create();

        $csv = $this->ynabCsv([
            ['Checking', '01/01/2025', 'Test', '', '', '$1.00', ''],
        ]);

        // UserA uploads
        $this->actingAs($userA)->post(route('import.ynab.upload'), ['csv_file' => $csv]);

        // UserA tries to map to UserB's account
        $this->actingAs($userA)
            ->post(route('import.ynab.commit'), [
                'account_map' => ['Checking' => (string) $account->id],
            ]);

        // No transaction should be created (account not found for userA)
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    private function ynabCsv(array $rows): UploadedFile
    {
        $header  = '"Account","Flag","Date","Payee","Category Group/Category","Category Group","Category","Memo","Outflow","Inflow","Cleared"';
        $lines   = [$header];

        foreach ($rows as [$account, $date, $payee, $category, $memo, $outflow, $inflow]) {
            $lines[] = "\"{$account}\",\"\",\"{$date}\",\"{$payee}\",\"\",\"\",\"{$category}\",\"{$memo}\",\"{$outflow}\",\"{$inflow}\",\"Cleared\"";
        }

        $content = implode("\n", $lines) . "\n";
        $tmp     = tempnam(sys_get_temp_dir(), 'ynab_test_') . '.csv';
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, 'ynab-export.csv', 'text/csv', null, true);
    }
}
