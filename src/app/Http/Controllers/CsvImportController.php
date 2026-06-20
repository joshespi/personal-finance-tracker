<?php

namespace App\Http\Controllers;

use App\Services\CsvImportService;
use App\Support\ImportPresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CsvImportController extends Controller
{
    public function index(): View
    {
        return view('import.csv');
    }

    public function upload(Request $request, CsvImportService $importer): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        [$jsonPath, $parsed] = $importer->parseAndStore($request->file('csv_file')->getRealPath(), $request->user()->id);

        if (empty($parsed['rows'])) {
            Storage::delete($jsonPath);

            return back()->withErrors(['csv_file' => 'No data rows found. Make sure the file is a CSV with a header row and at least one transaction.']);
        }

        session(['csv_import_path' => $jsonPath]);

        return redirect()->route('import.csv.preview');
    }

    public function preview(Request $request, CsvImportService $importer): View|RedirectResponse
    {
        if (! $jsonPath = $this->activeImportPath()) {
            return redirect()->route('import.csv')
                ->withErrors(['csv_file' => 'No import in progress. Please upload your CSV again.']);
        }

        ['headers' => $headers, 'rows' => $rows] = $importer->load($jsonPath);

        $userAccounts = $request->user()->cashAccounts()->orderBy('name')->get(['id', 'name']);
        $presets      = ImportPresets::all();
        $detected     = ImportPresets::detect($headers);
        $fields       = ImportPresets::fieldList();

        return view('import.csv-preview', compact('headers', 'rows', 'userAccounts', 'presets', 'detected', 'fields'));
    }

    public function commit(Request $request, CsvImportService $importer): RedirectResponse
    {
        if (! $jsonPath = $this->activeImportPath()) {
            return redirect()->route('import.csv')
                ->withErrors(['csv_file' => 'Import session expired. Please upload again.']);
        }

        $validated = $request->validate([
            'columns'         => ['required', 'array'],
            'columns.date'    => ['required', 'string'],
            'columns.*'       => ['nullable', 'string'],
            'date_format'     => ['required', 'string'],
            'account_map'     => ['array'],
            'account_map.*'   => ['string'],
            'default_account' => ['nullable', 'string'],
        ]);

        $columns = $validated['columns'];

        // Need somewhere to read the amount from.
        if (empty($columns['amount']) && empty($columns['inflow']) && empty($columns['outflow'])) {
            throw ValidationException::withMessages([
                'columns.amount' => 'Map either a single Amount column or an Inflow/Outflow pair.',
            ]);
        }

        // Need a destination: either a per-row account column or a single chosen account.
        if (empty($columns['account']) && empty($validated['default_account'])) {
            throw ValidationException::withMessages([
                'default_account' => 'Choose an account to import into, or map an account column.',
            ]);
        }

        $rows  = $importer->load($jsonPath)['rows'];
        $count = $importer->import($rows, $request->only([
            'columns', 'date_format', 'account_map', 'default_account', 'default_account_name',
        ]), $request->user());

        Storage::delete($jsonPath);
        session()->forget('csv_import_path');

        return redirect()->route('cash-accounts.index')
            ->with('success', "Import complete — {$count} transactions imported.");
    }

    public function cancel(): RedirectResponse
    {
        $jsonPath = session()->get('csv_import_path');
        if (is_string($jsonPath)) {
            Storage::delete($jsonPath);
            session()->forget('csv_import_path');
        }

        return redirect()->route('import.csv');
    }

    private function activeImportPath(): ?string
    {
        $path = session()->get('csv_import_path');
        if (! is_string($path) || ! Storage::exists($path)) {
            return null;
        }

        return $path;
    }
}
