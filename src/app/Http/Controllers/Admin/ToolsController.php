<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ToolsController extends Controller
{
    public function index(): View
    {
        return view('admin.tools');
    }

    public function backfillSnapshots(Request $request): RedirectResponse
    {
        $request->validate([
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
            'portfolio'  => ['nullable', 'string', 'regex:/^[\d,]+$/'],
        ]);

        $args = ['--no-interaction' => true];

        if ($request->filled('from'))      { $args['--from']      = $request->input('from'); }
        if ($request->filled('to'))        { $args['--to']        = $request->input('to'); }
        if ($request->filled('portfolio')) { $args['--portfolio'] = $request->input('portfolio'); }
        if ($request->boolean('skip_fetch')) { $args['--skip-fetch'] = true; }
        if ($request->boolean('dry_run'))    { $args['--dry-run']    = true; }

        Artisan::call('portfolios:backfill-snapshots', $args);
        $output = trim(Artisan::output());

        return redirect()->route('admin.tools')->with('output', $output ?: 'Done.');
    }
}
