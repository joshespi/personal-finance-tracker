<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\Portfolio;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        ['headers' => $headers, 'rows' => $raw] = $this->csv->parseCsv($path);

        // The trailing notes column is optional.
        if (count($headers) < count(self::COLUMNS) - 1) {
            return ['rows' => [], 'errors' => ['Invalid CSV format. Please use the provided template.']];
        }

        $rows   = [];
        $errors = [];

        foreach ($raw as $i => $row) {
            // Duplicate header names collapse keys in parseCsv's array_combine —
            // pad so a malformed file yields row errors instead of a 500.
            $cells = array_pad(array_values($row), count(self::COLUMNS) - 1, '');

            $data = [
                'date'           => $cells[0],
                'symbol'         => AssetService::normalizeSymbol($cells[1]),
                'asset_type'     => $cells[2],
                'type'           => $cells[3],
                'quantity'       => $cells[4],
                'price_per_unit' => $cells[5],
                'fees'           => $cells[6] ?: '0',
                'currency'       => strtoupper($cells[7]),
                'notes'          => $cells[8] ?? '',
            ];

            $v = Validator::make($data, [
                'date'           => ['required', 'date_format:Y-m-d'],
                'symbol'         => ['required', 'string', 'max:20'],
                'asset_type'     => ['required', Rule::enum(AssetType::class)],
                'type'           => ['required', Rule::enum(TransactionType::class)],
                'quantity'       => ['required', 'numeric', 'gt:0'],
                'price_per_unit' => ['required', 'numeric', 'gte:0'],
                'fees'           => ['numeric', 'gte:0'],
                'currency'       => ['required', 'string', 'size:3'],
            ]);

            if ($v->fails()) {
                $lineNum = $i + 2; // data rows start on line 2, after the header
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
                $asset = AssetService::findOrCreateBySymbol($row['symbol'], $row['asset_type']);
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
