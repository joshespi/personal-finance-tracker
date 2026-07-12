<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Services\TransactionCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionImportController extends Controller
{
    public function template(): Response
    {
        $headers = implode(',', TransactionCsvImportService::COLUMNS)."\n";
        $example = "2024-01-15,BTC,crypto,buy,0.5,40000,10,USD,initial purchase\n"
                 ."2024-03-20,AAPL,stock,buy,10,180.50,1.99,USD,\n";

        return response($headers.$example, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions-template.csv"',
        ]);
    }

    public function store(Request $request, Portfolio $portfolio, TransactionCsvImportService $importer): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        ['rows' => $rows, 'errors' => $errors] = $importer->parse($request->file('csv_file')->getRealPath());

        if (! empty($errors)) {
            return back()->withErrors(['csv_file' => $errors]);
        }

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'No valid rows found in the CSV.']);
        }

        $count = $importer->import($portfolio, $rows);

        return redirect()
            ->route('portfolios.transactions.index', $portfolio)
            ->with('success', "{$count} transaction(s) imported successfully.");
    }
}
