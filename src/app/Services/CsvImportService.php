<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\User;
use App\Support\ImportPresets;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Source-agnostic CSV → cash-transaction importer. The upload step keeps every
 * column verbatim; a user-supplied column mapping (CSV header → app field) is
 * applied at commit time. YNAB, Mint, plain bank exports, etc. are all just
 * different mappings (see {@see ImportPresets}).
 */
class CsvImportService
{
    /**
     * Parse a CSV into a header list + raw associative rows and store as JSON.
     *
     * @return array{0: string, 1: array{headers: list<string>, rows: list<array<string,string>>}}
     */
    public function parseAndStore(string $uploadedPath, int $userId): array
    {
        $parsed   = $this->parseCsv($uploadedPath);
        $jsonPath = "csv-imports/parsed-{$userId}.json";
        Storage::put($jsonPath, json_encode($parsed));

        return [$jsonPath, $parsed];
    }

    /** @return array{headers: list<string>, rows: list<array<string,string>>} */
    public function load(string $jsonPath): array
    {
        $data = json_decode(Storage::get($jsonPath), true);

        return [
            'headers' => $data['headers'] ?? [],
            'rows'    => $data['rows'] ?? [],
        ];
    }

    /**
     * Apply a column mapping to the parsed rows and create cash transactions.
     *
     * $mapping keys:
     *   columns       — field => CSV header (date required; amount OR inflow/outflow; account/payee/category/memo optional)
     *   date_format   — PHP date() format the source uses (e.g. 'm/d/Y')
     *   account_map   — [csvAccountName => '<id>'|'new'|'skip'] when an account column is mapped
     *   default_account / default_account_name — destination when there is no account column
     *
     * @param  list<array<string,string>>  $rows
     */
    public function import(array $rows, array $mapping, User $user): int
    {
        $columns    = $mapping['columns'] ?? [];
        $dateFormat = $mapping['date_format'] ?? 'm/d/Y';
        $accountCol = $columns['account'] ?? '';
        $accountMap = $mapping['account_map'] ?? [];

        $count        = 0;
        $accountCache = [];

        DB::transaction(function () use ($rows, $columns, $dateFormat, $accountCol, $accountMap, $mapping, $user, &$count, &$accountCache) {
            foreach ($rows as $raw) {
                $tx = $this->transformRow($raw, $columns, $dateFormat);
                if ($tx === null) {
                    continue;
                }

                // Resolve the destination account: per-row from the account column,
                // or a single user-chosen account when no such column is mapped.
                if ($accountCol !== '') {
                    $name     = trim($raw[$accountCol] ?? '');
                    $mapValue = $accountMap[$name] ?? null;
                    $cacheKey = $name;
                } else {
                    $mapValue = $mapping['default_account'] ?? null;
                    $name     = $mapping['default_account_name'] ?? 'Imported';
                    $cacheKey = '__default__';
                }

                if (! $mapValue || $mapValue === 'skip') {
                    continue;
                }

                if (! isset($accountCache[$cacheKey])) {
                    $account = $this->resolveAccount($user, $mapValue, $name);
                    if (! $account) {
                        continue;
                    }
                    $accountCache[$cacheKey] = $account;
                }

                $accountCache[$cacheKey]->transactions()->create([
                    'type'        => $tx['type'],
                    'amount'      => $tx['amount'],
                    'description' => substr($tx['description'], 0, 500),
                    'occurred_at' => $tx['date'],
                ]);

                $count++;
            }
        });

        return $count;
    }

    private function resolveAccount(User $user, string $mapValue, string $name): ?CashAccount
    {
        if ($mapValue === 'new') {
            return $user->cashAccounts()->create([
                'name'         => $name !== '' ? $name : 'Imported',
                'account_type' => 'checking',
                'currency'     => 'USD',
            ]);
        }

        return $user->cashAccounts()->find((int) $mapValue);
    }

    /**
     * Turn one raw row into a cash-transaction payload, or null if it carries no
     * amount (e.g. a YNAB split header or a zero-value row).
     *
     * @param  array<string,string>  $raw
     * @param  array<string,string>  $columns
     * @return array{date:string,type:string,amount:float,description:string}|null
     */
    private function transformRow(array $raw, array $columns, string $dateFormat): ?array
    {
        $get = function (string $field) use ($columns, $raw): string {
            $header = $columns[$field] ?? '';

            return $header !== '' ? trim($raw[$header] ?? '') : '';
        };

        // A single signed amount column wins; otherwise fall back to an inflow/outflow pair.
        if (($columns['amount'] ?? '') !== '') {
            $signed = $this->parseSignedAmount($get('amount'));
            if ($signed === 0.0) {
                return null;
            }
            $type   = $signed >= 0 ? 'deposit' : 'withdrawal';
            $amount = abs($signed);
        } else {
            $inflow  = $this->parseAmount($get('inflow'));
            $outflow = $this->parseAmount($get('outflow'));
            if ($inflow == 0.0 && $outflow == 0.0) {
                return null;
            }
            $type   = $inflow > 0 ? 'deposit' : 'withdrawal';
            $amount = $inflow > 0 ? $inflow : $outflow;
        }

        return [
            'date'        => $this->parseDate($get('date'), $dateFormat),
            'type'        => $type,
            'amount'      => $amount,
            'description' => $this->buildDescription($get('payee'), $get('category'), $get('memo')),
        ];
    }

    private function parseAmount(string $val): float
    {
        $cleaned = preg_replace('/[^0-9.]/', '', $val);

        return $cleaned !== '' ? (float) $cleaned : 0.0;
    }

    /** Parse an amount that may be signed via a leading '-' or surrounding parentheses. */
    private function parseSignedAmount(string $val): float
    {
        $val      = trim($val);
        $negative = str_starts_with($val, '-') || (str_starts_with($val, '(') && str_ends_with($val, ')'));

        return $negative ? -$this->parseAmount($val) : $this->parseAmount($val);
    }

    private function parseDate(string $val, string $format): string
    {
        $val = trim($val);

        try {
            return Carbon::createFromFormat($format, $val)->format('Y-m-d');
        } catch (\Exception) {
            // Fall back to loose parsing (handles ISO and other common shapes).
            try {
                return Carbon::parse($val)->format('Y-m-d');
            } catch (\Exception) {
                return now()->format('Y-m-d');
            }
        }
    }

    private function buildDescription(string $payee, string $category, string $memo): string
    {
        return implode(' · ', array_filter([$payee, $category, $memo]));
    }

    /**
     * Generic CSV reader: strips a UTF-8 BOM, skips blank rows, trims cells, and
     * normalises each row to the header width. Also used by {@see TransactionCsvImportService}.
     * `lineNumbers[$i]` is the 1-based physical file line that `rows[$i]` came from
     * (blank lines are dropped from `rows` but still counted), so callers reporting
     * per-row errors don't have to re-derive it from the filtered array index.
     *
     * @return array{headers: list<string>, rows: list<array<string,string>>, lineNumbers: list<int>}
     */
    public function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $headers      = null;
        $rows         = [];
        $lineNumbers  = [];
        $physicalLine = 0;

        while (($line = fgetcsv($handle)) !== false) {
            $physicalLine++;

            if (array_filter($line) === []) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map('trim', $line);

                continue;
            }

            // Normalise the row to the header width so array_combine is always safe.
            $width = count($headers);
            $line  = array_pad(array_slice($line, 0, $width), $width, '');

            $rows[]        = array_combine($headers, array_map('trim', $line));
            $lineNumbers[] = $physicalLine;
        }

        fclose($handle);

        return ['headers' => $headers ?? [], 'rows' => $rows, 'lineNumbers' => $lineNumbers];
    }
}
