<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    public function test_import_index_requires_auth(): void
    {
        $this->get(route('import.csv'))->assertRedirect(route('login'));
    }

    public function test_upload_requires_auth(): void
    {
        $this->post(route('import.csv.upload'))->assertRedirect(route('login'));
    }

    public function test_upload_parses_csv_and_redirects_to_preview(): void
    {
        $user = User::factory()->create();
        $csv  = $this->ynabCsv([
            ['Checking', '01/15/2025', 'Grocery Store', 'Food', '', '$45.00', ''],
            ['Checking', '01/16/2025', 'Paycheck', '', '', '', '$2,000.00'],
        ]);

        $this->actingAs($user)
            ->post(route('import.csv.upload'), ['csv_file' => $csv])
            ->assertRedirect(route('import.csv.preview'));
    }

    public function test_upload_rejects_empty_csv(): void
    {
        $user = User::factory()->create();
        $csv  = $this->ynabCsv([]);

        $this->actingAs($user)
            ->post(route('import.csv.upload'), ['csv_file' => $csv])
            ->assertRedirect()
            ->assertSessionHasErrors('csv_file');
    }

    public function test_preview_autodetects_ynab_preset(): void
    {
        $user = User::factory()->create();
        $csv  = $this->ynabCsv([
            ['Checking', '01/15/2025', 'Coffee', 'Dining', '', '$4.50', ''],
        ]);

        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->get(route('import.csv.preview'))
            ->assertOk()
            ->assertViewHas('detected', 'ynab')
            ->assertViewHas('headers');
    }

    public function test_commit_creates_transactions_for_mapped_accounts(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create(['name' => 'My Checking']);

        $csv = $this->ynabCsv([
            ['Checking', '01/15/2025', 'Coffee', 'Dining', 'morning coffee', '$4.50', ''],
            ['Checking', '01/16/2025', 'Paycheck', '', '', '', '$3,000.00'],
        ]);

        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.csv.commit'), $this->ynabMapping(['Checking' => (string) $account->id]))
            ->assertRedirect(route('cash-accounts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'withdrawal', 'amount' => 4.50, 'cash_account_id' => $account->id]);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'deposit', 'amount' => 3000.00, 'cash_account_id' => $account->id]);
    }

    public function test_commit_builds_description_from_payee_category_memo(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        $csv = $this->ynabCsv([
            ['Checking', '01/15/2025', 'Coffee', 'Dining', 'morning coffee', '$4.50', ''],
        ]);

        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);
        $this->actingAs($user)->post(route('import.csv.commit'), $this->ynabMapping(['Checking' => (string) $account->id]));

        $this->assertDatabaseHas('cash_transactions', ['description' => 'Coffee · Dining · morning coffee']);
    }

    public function test_commit_creates_new_account_when_map_value_is_new(): void
    {
        $user = User::factory()->create();

        $csv = $this->ynabCsv([
            ['Savings', '02/01/2025', 'Transfer', '', '', '', '$500.00'],
        ]);

        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.csv.commit'), $this->ynabMapping(['Savings' => 'new']))
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
            ['Credit', '01/01/2025', 'Amazon', '', '', '$99.00', ''],
        ]);

        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)->post(route('import.csv.commit'), $this->ynabMapping([
            'Checking' => (string) $account->id,
            'Credit'   => 'skip',
        ]));

        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseHas('cash_transactions', ['amount' => 5.00]);
    }

    public function test_commit_with_single_signed_amount_and_default_account(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        $csv = $this->genericCsv([
            ['2025-03-01', 'Refund', '120.00'],
            ['2025-03-02', 'Groceries', '-45.30'],
            ['2025-03-03', 'ATM', '(60.00)'],
        ]);

        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.csv.commit'), [
                'columns'         => ['date' => 'Date', 'payee' => 'Description', 'amount' => 'Amount'],
                'date_format'     => 'Y-m-d',
                'default_account' => (string) $account->id,
            ])
            ->assertRedirect(route('cash-accounts.index'));

        $this->assertDatabaseCount('cash_transactions', 3);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'deposit', 'amount' => 120.00, 'cash_account_id' => $account->id]);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'withdrawal', 'amount' => 45.30, 'cash_account_id' => $account->id]);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'withdrawal', 'amount' => 60.00, 'occurred_at' => '2025-03-03 00:00:00']);
    }

    public function test_commit_requires_an_amount_source(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        $csv = $this->genericCsv([['2025-03-01', 'Refund', '120.00']]);
        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.csv.commit'), [
                'columns'         => ['date' => 'Date', 'payee' => 'Description'],
                'date_format'     => 'Y-m-d',
                'default_account' => (string) $account->id,
            ])
            ->assertSessionHasErrors('columns.amount');

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_cancel_clears_session_and_temp_file(): void
    {
        $user = User::factory()->create();
        $csv  = $this->ynabCsv([
            ['Checking', '01/01/2025', 'Test', '', '', '$1.00', ''],
        ]);

        $this->actingAs($user)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        $this->actingAs($user)
            ->post(route('import.csv.cancel'))
            ->assertRedirect(route('import.csv'));

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

        $this->actingAs($userA)->post(route('import.csv.upload'), ['csv_file' => $csv]);

        // UserA tries to map to UserB's account — find() is scoped to userA, so nothing imports.
        $this->actingAs($userA)
            ->post(route('import.csv.commit'), $this->ynabMapping(['Checking' => (string) $account->id]));

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    /** Commit payload mapping the YNAB columns (YNAB is now just a preset). */
    private function ynabMapping(array $accountMap): array
    {
        return [
            'columns' => [
                'account'  => 'Account',
                'date'     => 'Date',
                'payee'    => 'Payee',
                'category' => 'Category',
                'memo'     => 'Memo',
                'outflow'  => 'Outflow',
                'inflow'   => 'Inflow',
            ],
            'date_format' => 'm/d/Y',
            'account_map' => $accountMap,
        ];
    }

    private function ynabCsv(array $rows): UploadedFile
    {
        $header = '"Account","Flag","Date","Payee","Category Group/Category","Category Group","Category","Memo","Outflow","Inflow","Cleared"';
        $lines  = [$header];

        foreach ($rows as [$account, $date, $payee, $category, $memo, $outflow, $inflow]) {
            $lines[] = "\"{$account}\",\"\",\"{$date}\",\"{$payee}\",\"\",\"\",\"{$category}\",\"{$memo}\",\"{$outflow}\",\"{$inflow}\",\"Cleared\"";
        }

        return $this->uploadedCsv(implode("\n", $lines)."\n");
    }

    private function genericCsv(array $rows): UploadedFile
    {
        $lines = ['Date,Description,Amount'];

        foreach ($rows as [$date, $desc, $amount]) {
            $lines[] = "{$date},{$desc},\"{$amount}\"";
        }

        return $this->uploadedCsv(implode("\n", $lines)."\n");
    }

    private function uploadedCsv(string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv_test_').'.csv';
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, 'import.csv', 'text/csv', null, true);
    }
}
