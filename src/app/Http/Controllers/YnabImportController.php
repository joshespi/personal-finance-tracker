<?php

namespace App\Http\Controllers;

use App\Services\YnabImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class YnabImportController extends Controller
{
    public function index(): View
    {
        return view('import.ynab');
    }

    public function upload(Request $request, YnabImportService $importer): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $uploadedPath = $request->file('csv_file')->getRealPath();
        $jsonPath     = $importer->parseAndStore($uploadedPath, $request->user()->id);

        $rows = $importer->load($jsonPath);

        if (empty($rows)) {
            Storage::delete($jsonPath);
            return back()->withErrors(['csv_file' => 'No valid transactions found. Make sure this is a YNAB "All Transactions" CSV export.']);
        }

        session(['ynab_json_path' => $jsonPath]);

        return redirect()->route('import.ynab.preview');
    }

    public function preview(Request $request, YnabImportService $importer): View|RedirectResponse
    {
        $jsonPath = session('ynab_json_path');

        if (!$jsonPath || !Storage::exists($jsonPath)) {
            return redirect()->route('import.ynab')
                ->withErrors(['csv_file' => 'No import in progress. Please upload your CSV again.']);
        }

        $rows         = $importer->load($jsonPath);
        $ynabAccounts = collect($rows)->pluck('account')->unique()->sort()->values();
        $userAccounts = $request->user()->cashAccounts()->orderBy('name')->get(['id', 'name']);

        return view('import.ynab-preview', compact('rows', 'ynabAccounts', 'userAccounts'));
    }

    public function commit(Request $request, YnabImportService $importer): RedirectResponse
    {
        $jsonPath = session('ynab_json_path');

        if (!$jsonPath || !Storage::exists($jsonPath)) {
            return redirect()->route('import.ynab')
                ->withErrors(['csv_file' => 'Import session expired. Please upload again.']);
        }

        $request->validate([
            'account_map'   => ['required', 'array'],
            'account_map.*' => ['required', 'string'],
        ]);

        $rows  = $importer->load($jsonPath);
        $count = $importer->import($rows, $request->input('account_map'), $request->user());

        Storage::delete($jsonPath);
        session()->forget('ynab_json_path');

        return redirect()->route('cash-accounts.index')
            ->with('success', "YNAB import complete — {$count} transactions imported.");
    }

    public function cancel(): RedirectResponse
    {
        $jsonPath = session('ynab_json_path');
        if ($jsonPath) {
            Storage::delete($jsonPath);
            session()->forget('ynab_json_path');
        }

        return redirect()->route('import.ynab');
    }
}
