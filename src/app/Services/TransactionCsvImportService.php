<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;

/** Per-portfolio transactions CSV importer (see routes/web.php's portfolios.transactions.import). */
class TransactionCsvImportService
{
    /** Template column order — the download in TransactionImportController::template() and parse() both derive from this. */
    public const COLUMNS = ['date', 'symbol', 'asset_type', 'type', 'quantity', 'price_per_unit', 'fees', 'currency', 'notes'];

    public function __construct(private CsvImportService $csv) {}

    /**
     * Parse and validate an uploaded transactions CSV.
     *
     * @return array{rows: list<array>, errors: list<string>}
     */
    public function parse(string $path): array
    {
        ['headers' => $headers, 'rows' => $raw, 'lineNumbers' => $lineNumbers] = $this->csv->parseCsv($path);

        // 'notes' is the only optional column. A duplicate header name would make
        // array_combine silently drop a column and misalign every field after it,
        // so reject that up front rather than importing shifted data.
        $requiredHeaders = array_slice(self::COLUMNS, 0, -1);
        $hasDuplicates   = count($headers) !== count(array_unique($headers));
        if ($hasDuplicates || array_diff($requiredHeaders, $headers) !== []) {
            return ['rows' => [], 'errors' => ['Invalid CSV format. Please use the provided template.']];
        }

        $rows   = [];
        $errors = [];

        $rules = Transaction::fieldRules() + [
            'date' => ['required', 'date_format:Y-m-d'],
        ];

        foreach ($raw as $i => $row) {
            $data = [
                'date'           => $row['date'],
                'symbol'         => AssetService::normalizeSymbol($row['symbol']),
                'asset_type'     => $row['asset_type'],
                'type'           => $row['type'],
                'quantity'       => $row['quantity'],
                'price_per_unit' => $row['price_per_unit'],
                'fees'           => $row['fees'] ?: '0',
                'currency'       => strtoupper($row['currency']),
                'notes'          => $row['notes'] ?? '',
            ];

            $v = Validator::make($data, $rules);

            if ($v->fails()) {
                $lineNum = $lineNumbers[$i];
                foreach ($v->errors()->all() as $msg) {
                    $errors[] = "Row {$lineNum}: {$msg}";
                }

                continue;
            }

            $rows[] = $data;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Create portfolio transactions from rows already validated by parse(),
     * creating any assets that don't exist yet.
     *
     * @param  list<array>  $rows
     * @return int number of transactions created
     */
    public function import(Portfolio $portfolio, array $rows): int
    {
        // One lookup for all symbols instead of a firstOrCreate SELECT per row —
        // real exports repeat the same few symbols hundreds of times.
        $assets = Asset::whereIn('symbol', array_unique(array_column($rows, 'symbol')))
            ->get()
            ->keyBy('symbol');

        foreach ($rows as $row) {
            $asset = $assets->get($row['symbol']);
            if (! $asset) {
                // The lookup above already proved this symbol is absent, so create directly
                // rather than findOrCreateBySymbol()'s redundant existence-check SELECT.
                $asset = AssetService::createBySymbol($row['symbol'], $row['asset_type']);
                $assets->put($row['symbol'], $asset);
            }

            $portfolio->transactions()->create([
                'asset_id'       => $asset->id,
                'type'           => $row['type'],
                'quantity'       => $row['quantity'],
                'price_per_unit' => $row['price_per_unit'],
                'fees'           => $row['fees'],
                'currency'       => $row['currency'],
                'transacted_at'  => $row['date'],
                'notes'          => $row['notes'] ?: null,
            ]);
        }

        return count($rows);
    }
}
