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

        // JSON_THROW_ON_ERROR so a file we somehow still can't encode fails loudly here.
        // Without it json_encode() returns false, Storage::put() writes an empty file, and
        // the user gets an inexplicably blank preview two steps later. parseCsv() already
        // transcodes non-UTF-8 input, so reaching this is a bug, not a bad upload.
        Storage::put($jsonPath, json_encode($parsed, JSON_THROW_ON_ERROR));

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

        // A single signed amount column wins; otherwise net an inflow/outflow pair. Sources
        // using the pair populate exactly one per row — when both carry a value the row is a
        // net movement, not two events, so net them rather than taking inflow and silently
        // dropping the outflow. Either way a row that moves nothing isn't a transaction.
        $signed = ($columns['amount'] ?? '') !== ''
            ? $this->parseSignedAmount($get('amount'))
            : $this->parseAmount($get('inflow')) - $this->parseAmount($get('outflow'));

        if ($signed == 0.0) {
            return null;
        }

        $type   = $signed > 0 ? 'deposit' : 'withdrawal';
        $amount = abs($signed);

        $date = $this->parseDate($get('date'), $dateFormat);
        if ($date === null) {
            return null;
        }

        return [
            'date'        => $date,
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

    /** Returns null (row skipped by the caller) rather than guessing a date for an unparseable cell. */
    private function parseDate(string $val, string $format): ?string
    {
        $val = trim($val);
        if ($val === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat($format, $val)->format('Y-m-d');
        } catch (\Exception) {
            // Fall back to loose parsing (handles ISO and other common shapes).
            try {
                return Carbon::parse($val)->format('Y-m-d');
            } catch (\Exception) {
                return null;
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

        if ($handle === false) {
            throw new \RuntimeException("Could not open uploaded CSV for reading: {$path}");
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $headers      = null;
        $rows         = [];
        $lineNumbers  = [];
        $physicalLine = 0;
        // Bound once, not per row — a large import parses tens of thousands of lines.
        $normalize = $this->normalizeCell(...);

        while (($line = fgetcsv($handle)) !== false) {
            $physicalLine++;

            if (array_filter($line) === []) {
                continue;
            }

            $line = array_map($normalize, $line);

            if ($headers === null) {
                $headers = $line;

                continue;
            }

            // Normalise the row to the header width so array_combine is always safe.
            $width = count($headers);
            $line  = array_pad(array_slice($line, 0, $width), $width, '');

            $rows[]        = array_combine($headers, $line);
            $lineNumbers[] = $physicalLine;
        }

        fclose($handle);

        return ['headers' => $headers ?? [], 'rows' => $rows, 'lineNumbers' => $lineNumbers];
    }

    /**
     * Trim a cell and force it to valid UTF-8. Bank exports are routinely Windows-1252
     * (a currency symbol or an accented payee is enough), which json_encode() cannot
     * represent — it returns false, the parsed file is written empty, and the failure
     * only surfaces as an unexplained blank preview. Transcoding at the source keeps
     * every downstream consumer on clean UTF-8.
     */
    private function normalizeCell(?string $value): string
    {
        $value ??= '';

        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return trim($value);
    }
}
