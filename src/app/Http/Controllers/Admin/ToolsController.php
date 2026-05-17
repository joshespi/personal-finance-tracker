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

        foreach (['from', 'to', 'portfolio'] as $field) {
            if ($request->filled($field)) {
                $args['--' . $field] = $request->input($field);
            }
        }

        if ($request->boolean('skip_fetch')) { $args['--skip-fetch'] = true; }
        if ($request->boolean('dry_run'))    { $args['--dry-run']    = true; }

        set_time_limit(600);
        Artisan::call('portfolios:backfill-snapshots', $args);
        $output = trim(Artisan::output());

        return redirect()->route('admin.tools')->with('output', $output ?: 'Done.');
    }
}
