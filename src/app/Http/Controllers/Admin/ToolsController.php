<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackfillRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ToolsController extends Controller
{
    public function index(): View
    {
        $backfillRequests = BackfillRequest::latest()->limit(5)->get();

        return view('admin.tools', compact('backfillRequests'));
    }

    public function backfillSnapshots(Request $request): RedirectResponse
    {
        $request->validate([
            'from'      => ['nullable', 'date'],
            'to'        => ['nullable', 'date'],
            'portfolio' => ['nullable', 'string', 'regex:/^[\d,]+$/'],
        ]);

        $args = ['--no-interaction' => true];

        foreach (['from', 'to', 'portfolio'] as $field) {
            if ($request->filled($field)) {
                $args['--'.$field] = $request->input($field);
            }
        }

        $skipFetch = $request->boolean('skip_fetch');
        $dryRun    = $request->boolean('dry_run');

        if ($skipFetch) {
            $args['--skip-fetch'] = true;
        }
        if ($dryRun) {
            $args['--dry-run'] = true;
        }

        // Fetching historical prices can run long enough to hit provider rate limits or a
        // proxy timeout, so it's queued unless Skip fetch is checked — skip-fetch makes no
        // API calls at all, so it's safe (and useful for a quick recompute after fixing a
        // price) to run inline here, backed by the timeout below.
        if (! $skipFetch && ! $dryRun) {
            $args['--queue'] = true;
        }

        set_time_limit(600);
        Artisan::call('portfolios:backfill-snapshots', $args);
        $output = trim(Artisan::output());

        return redirect()->route('admin.tools')->with('output', $output ?: 'Done.');
    }
}
