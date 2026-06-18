<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class YnabImportService
{
    /** @return array{0: string, 1: list<array<string, string>>} */
    public function parseAndStore(string $uploadedPath, int $userId): array
    {
        $rows     = $this->parseCsv($uploadedPath);
        $jsonPath = "ynab-imports/parsed-{$userId}.json";
        Storage::put($jsonPath, json_encode($rows));

        return [$jsonPath, $rows];
    }

    public function load(string $jsonPath): array
    {
        return json_decode(Storage::get($jsonPath), true) ?? [];
    }

    public function import(array $rows, array $accountMap, User $user): int
    {
        $count        = 0;
        $accountCache = [];

        DB::transaction(function () use ($rows, $accountMap, $user, &$count, &$accountCache) {
            foreach ($rows as $row) {
                $ynabName = $row['account'];
                $mapValue = $accountMap[$ynabName] ?? null;

                if (! $mapValue || $mapValue === 'skip') {
                    continue;
                }

                if (! isset($accountCache[$ynabName])) {
                    if ($mapValue === 'new') {
                        $accountCache[$ynabName] = $user->cashAccounts()->create([
                            'name'         => $ynabName,
                            'account_type' => 'checking',
                            'currency'     => 'USD',
                        ]);
                    } else {
                        $accountCache[$ynabName] = $user->cashAccounts()->find((int) $mapValue);
                        if (! $accountCache[$ynabName]) {
                            continue;
                        }
                    }
                }

                $account = $accountCache[$ynabName];
                $desc    = $this->buildDescription($row);

                $account->transactions()->create([
                    'type'        => $row['type'],
                    'amount'      => $row['amount'],
                    'description' => substr($desc, 0, 500),
                    'occurred_at' => $row['date'],
                ]);

                $count++;
            }
        });

        return $count;
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $header = null;
        $rows   = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (array_filter($line) === []) {
                continue;
            }

            if ($header === null) {
                $header = array_map('trim', $line);

                continue;
            }

            if (count($line) < 4) {
                continue;
            }

            $row = array_combine(
                array_slice($header, 0, count($line)),
                $line
            );

            $inflow  = $this->parseAmount($row['Inflow'] ?? '');
            $outflow = $this->parseAmount($row['Outflow'] ?? '');

            if ($inflow == 0.0 && $outflow == 0.0) {
                continue;
            }

            $rows[] = [
                'account'  => trim($row['Account'] ?? ''),
                'date'     => $this->parseDate($row['Date'] ?? ''),
                'payee'    => trim($row['Payee'] ?? ''),
                'category' => trim($row['Category'] ?? ''),
                'memo'     => trim($row['Memo'] ?? ''),
                'type'     => $inflow > 0 ? 'deposit' : 'withdrawal',
                'amount'   => $inflow > 0 ? $inflow : $outflow,
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function parseAmount(string $val): float
    {
        $cleaned = preg_replace('/[^0-9.]/', '', $val);

        return $cleaned !== '' ? (float) $cleaned : 0.0;
    }

    private function parseDate(string $val): string
    {
        try {
            return Carbon::createFromFormat('m/d/Y', trim($val))->format('Y-m-d');
        } catch (\Exception) {
            return now()->format('Y-m-d');
        }
    }

    private function buildDescription(array $row): string
    {
        $parts = array_filter([$row['payee'], $row['category'], $row['memo']]);

        return implode(' · ', $parts);
    }
}
