<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionImportController extends Controller
{
    private const VALID_TYPES = ['buy', 'sell', 'dividend', 'staking_reward', 'transfer_in', 'transfer_out'];

    public function template(): Response
    {
        $headers = "date,symbol,asset_type,type,quantity,price_per_unit,fees,currency,notes\n";
        $example = "2024-01-15,BTC,crypto,buy,0.5,40000,10,USD,initial purchase\n"
                 . "2024-03-20,AAPL,stock,buy,10,180.50,1.99,USD,\n";

        return response($headers . $example, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions-template.csv"',
        ]);
    }

    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path    = $request->file('csv_file')->getRealPath();
        $handle  = fopen($path, 'r');
        $header  = fgetcsv($handle); // skip header row

        if (! $header || count($header) < 8) {
            return back()->withErrors(['csv_file' => 'Invalid CSV format. Please use the provided template.']);
        }

        $rows     = [];
        $lineNum  = 1;
        $errors   = [];

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            if (count(array_filter($row)) === 0) {
                continue; // skip blank rows
            }

            $data = [
                'date'           => trim($row[0] ?? ''),
                'symbol'         => strtoupper(trim($row[1] ?? '')),
                'asset_type'     => trim($row[2] ?? ''),
                'type'           => trim($row[3] ?? ''),
                'quantity'       => trim($row[4] ?? ''),
                'price_per_unit' => trim($row[5] ?? ''),
                'fees'           => trim($row[6] ?? '') ?: '0',
                'currency'       => strtoupper(trim($row[7] ?? '')),
                'notes'          => trim($row[8] ?? ''),
            ];

            $v = Validator::make($data, [
                'date'           => ['required', 'date_format:Y-m-d'],
                'symbol'         => ['required', 'string', 'max:20'],
                'asset_type'     => ['required', Rule::in(['stock', 'crypto', 'real_estate'])],
                'type'           => ['required', Rule::in(self::VALID_TYPES)],
                'quantity'       => ['required', 'numeric', 'gt:0'],
                'price_per_unit' => ['required', 'numeric', 'gte:0'],
                'fees'           => ['numeric', 'gte:0'],
                'currency'       => ['required', 'string', 'size:3'],
            ]);

            if ($v->fails()) {
                foreach ($v->errors()->all() as $msg) {
                    $errors[] = "Row {$lineNum}: {$msg}";
                }
                continue;
            }

            $rows[] = $data;
        }

        fclose($handle);

        if (! empty($errors)) {
            return back()->withErrors(['csv_file' => $errors]);
        }

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'No valid rows found in the CSV.']);
        }

        foreach ($rows as $row) {
            $asset = Asset::firstOrCreate(
                ['symbol' => $row['symbol']],
                ['name' => $row['symbol'], 'asset_type' => $row['asset_type']]
            );

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

        $count = count($rows);

        return redirect()
            ->route('portfolios.transactions.index', $portfolio)
            ->with('success', "{$count} transaction(s) imported successfully.");
    }
}
