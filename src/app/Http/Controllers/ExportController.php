<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\RealizedGainService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Cell values that a spreadsheet would evaluate as a formula rather than display as
     * text. Portfolio/asset names and notes are free text, so a name like `=cmd|...` or
     * `+HYPERLINK(...)` becomes live code the moment the export is opened in Excel or
     * Sheets. Prefixing with an apostrophe is the standard neutralization: spreadsheets
     * treat the cell as text and don't render the apostrophe itself.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    private static function csvSafe(mixed $value): mixed
    {
        // is_numeric() first: Eloquent's decimal casts hand back *strings*, so a negative
        // quantity arrives as "-1.50000000" and would otherwise be quoted into text and
        // break every numeric column in the export.
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }

    /** Streams a dated CSV download: $header, then one fputcsv() row per entry of $rows, every cell run through csvSafe(). */
    private static function streamCsv(string $filenamePrefix, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_map(self::csvSafe(...), $header));

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::csvSafe(...), $row));
            }

            fclose($out);
        }, $filenamePrefix.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function index(): View
    {
        return view('export.index');
    }

    public function transactions(Request $request): StreamedResponse
    {
        $user = $request->user();

        $transactions = Transaction::with(['asset', 'portfolio'])
            ->whereIn('portfolio_id', $user->portfolios()->pluck('id'))
            ->orderBy('transacted_at')
            ->get();

        return self::streamCsv(
            'transactions',
            ['Date', 'Portfolio', 'Symbol', 'Type', 'Quantity', 'Price Per Unit', 'Fees', 'Currency', 'Total'],
            $transactions->map(fn ($t) => [
                $t->transacted_at->format('Y-m-d'),
                $t->portfolio->name,
                $t->asset->symbol,
                $t->type->value,
                $t->quantity,
                $t->price_per_unit,
                $t->fees,
                $t->currency,
                round($t->totalCost(), 2),
            ]),
        );
    }

    public function realizedGains(Request $request, RealizedGainService $realizedGainService): StreamedResponse
    {
        $allLots = $realizedGainService->allLotsForUser($request->user())->sortBy('sell_date');

        return self::streamCsv(
            'realized-gains',
            [
                'Symbol', 'Portfolio', 'Quantity',
                'Buy Date', 'Sell Date', 'Days Held', 'Term',
                'Cost Basis', 'Proceeds', 'Gain / Loss',
            ],
            $allLots->map(fn ($lot) => [
                $lot['asset']->symbol,
                $lot['portfolio']->name,
                $lot['quantity'],
                $lot['buy_date']->format('Y-m-d'),
                $lot['sell_date']->format('Y-m-d'),
                $lot['holding_days'],
                $lot['term'] === 'long' ? 'Long-term' : 'Short-term',
                $lot['cost_basis'],
                $lot['proceeds'],
                $lot['gain'],
            ]),
        );
    }

    public function fullBackup(Request $request): StreamedResponse
    {
        $user = $request->user();

        $portfolios = $user->portfolios()
            ->with([
                'transactions.asset',
                'manualAssets.valuations',
                'journalEntries',
            ])
            ->get();

        $liabilities = $user->liabilities()->with('balances')->get();

        $cashAccounts = $user->cashAccounts()->with('transactions.incomeCategory:id,name')->get();

        $pensions = $user->pensions()->orderBy('name')->get();

        $envelopes = $user->envelopes()->with('transactions')->get();

        $payload = [
            'exported_at'            => now()->toIso8601String(),
            'version'                => 1,
            'portfolios'             => $portfolios->map(fn ($p) => $p->toBackupArray())->values(),
            'liabilities'            => $liabilities->map(fn ($l) => $l->toBackupArray())->values(),
            'cash_accounts'          => $cashAccounts->map(fn ($a) => $a->toBackupArray())->values(),
            'pensions'               => $pensions->map(fn ($p) => $p->toBackupArray())->values(),
            'income_categories'      => $user->incomeCategories()->ordered()->get()->map(fn ($c) => $c->toBackupArray())->values(),
            'envelopes'              => $envelopes->map(fn ($e) => $e->toBackupArray())->values(),
            'scheduled_transactions' => $user->scheduledTransactions()->get()->map(fn ($s) => $s->toBackupArray())->values(),
        ];

        $filename = 'finance-backup-'.now()->format('Y-m-d').'.json';
        $json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
