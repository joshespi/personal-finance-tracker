<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function index(Request $request, Portfolio $portfolio): View
    {
        $this->authorizePortfolio($request, $portfolio);

        $entries = $portfolio->journalEntries()->get();

        return view('journal.index', compact('portfolio', 'entries'));
    }

    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorizePortfolio($request, $portfolio);

        $data = $request->validate([
            'title'      => ['nullable', 'string', 'max:255'],
            'body'       => ['required', 'string'],
            'entry_date' => ['required', 'date'],
        ]);

        $portfolio->journalEntries()->create($data);

        return redirect()->route('portfolios.journal.index', $portfolio)
            ->with('success', 'Entry added.');
    }

    public function edit(Request $request, Portfolio $portfolio, JournalEntry $entry): View
    {
        $this->authorizePortfolio($request, $portfolio);
        abort_if($entry->portfolio_id !== $portfolio->id, 403);

        return view('journal.edit', compact('portfolio', 'entry'));
    }

    public function update(Request $request, Portfolio $portfolio, JournalEntry $entry): RedirectResponse
    {
        $this->authorizePortfolio($request, $portfolio);
        abort_if($entry->portfolio_id !== $portfolio->id, 403);

        $data = $request->validate([
            'title'      => ['nullable', 'string', 'max:255'],
            'body'       => ['required', 'string'],
            'entry_date' => ['required', 'date'],
        ]);

        $entry->update($data);

        return redirect()->route('portfolios.journal.index', $portfolio)
            ->with('success', 'Entry updated.');
    }

    public function destroy(Request $request, Portfolio $portfolio, JournalEntry $entry): RedirectResponse
    {
        $this->authorizePortfolio($request, $portfolio);
        abort_if($entry->portfolio_id !== $portfolio->id, 403);

        $entry->delete();

        return redirect()->route('portfolios.journal.index', $portfolio)
            ->with('success', 'Entry deleted.');
    }

    private function authorizePortfolio(Request $request, Portfolio $portfolio): void
    {
        abort_if($portfolio->user_id !== $request->user()->id, 403);
    }
}
