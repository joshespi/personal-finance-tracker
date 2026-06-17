<?php

namespace App\Http\Controllers;

use App\Services\RealizedGainService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(): View
    {
        return view('export.index');
    }

    public function transactions(Request $request): StreamedResponse
    {
        $user = $request->user();

        $transactions = \App\Models\Transaction::with(['asset', 'portfolio'])
            ->whereIn('portfolio_id', $user->portfolios()->pluck('id'))
            ->orderBy('transacted_at')
            ->get();

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Date', 'Portfolio', 'Symbol', 'Type', 'Quantity', 'Price Per Unit', 'Fees', 'Currency', 'Total']);

            foreach ($transactions as $t) {
                fputcsv($out, [
                    $t->transacted_at->format('Y-m-d'),
                    $t->portfolio->name,
                    $t->asset->symbol,
                    $t->type,
                    $t->quantity,
                    $t->price_per_unit,
                    $t->fees,
                    $t->currency,
                    round($t->totalCost(), 2),
                ]);
            }

            fclose($out);
        }, 'transactions-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function realizedGains(Request $request): StreamedResponse
    {
        $user       = $request->user();
        $service    = new RealizedGainService();
        $portfolios = $user->portfolios()->where('is_tax_advantaged', false)->with('transactions.asset')->get();

        $allLots = collect();
        foreach ($portfolios as $portfolio) {
            $result  = $service->compute($portfolio);
            $allLots = $allLots->concat($result['lots']->map(fn ($l) => array_merge($l, ['portfolio' => $portfolio])));
        }

        $allLots = $allLots->sortBy('sell_date');

        return response()->streamDownload(function () use ($allLots) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Symbol', 'Portfolio', 'Quantity',
                'Buy Date', 'Sell Date', 'Days Held', 'Term',
                'Cost Basis', 'Proceeds', 'Gain / Loss',
            ]);

            foreach ($allLots as $lot) {
                fputcsv($out, [
                    $lot['asset']->symbol,
                    $lot['portfolio']->name,
                    $lot['quantity'],
                    $lot['buy_date']->format('Y-m-d'),
                    $lot['sell_date']->format('Y-m-d'),
                    $lot['holding_days'],
                    $lot['holding_days'] >= 365 ? 'Long-term' : 'Short-term',
                    $lot['cost_basis'],
                    $lot['proceeds'],
                    $lot['gain'],
                ]);
            }

            fclose($out);
        }, 'realized-gains-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function fullBackup(Request $request): StreamedResponse
    {
        $user = $request->user();

        $portfolios = $user->portfolios()
            ->with([
                'transactions.asset',
                'manualAssets.valuations',
                'journalEntries',
            ])
            ->get();

        $liabilities = $user->liabilities()->with('balances')->get();

        $cashAccounts = $user->cashAccounts()->with('transactions')->get();

        $envelopes = $user->envelopes()->with('transactions')->get();

        $payload = [
            'exported_at'          => now()->toIso8601String(),
            'version'              => 1,
            'portfolios'           => $portfolios->map(fn ($p) => [
                'name'             => $p->name,
                'description'      => $p->description,
                'currency'         => $p->currency,
                'is_tax_advantaged' => (bool) $p->is_tax_advantaged,
                'created_at'       => $p->created_at->toDateString(),
                'transactions'     => $p->transactions->map(fn ($t) => [
                    'date'           => $t->transacted_at->toDateString(),
                    'symbol'         => $t->asset->symbol,
                    'asset_type'     => $t->asset->asset_type,
                    'type'           => $t->type,
                    'quantity'       => (float) $t->quantity,
                    'price_per_unit' => (float) $t->price_per_unit,
                    'fees'           => (float) $t->fees,
                    'currency'       => $t->currency,
                    'notes'          => $t->notes,
                ])->values(),
                'manual_assets'    => $p->manualAssets->map(fn ($m) => [
                    'name'            => $m->name,
                    'asset_class'     => $m->asset_class,
                    'cost_basis'      => $m->cost_basis !== null ? (float) $m->cost_basis : null,
                    'currency'        => $m->currency,
                    'tracking_method' => $m->tracking_method,
                    'description'     => $m->description,
                    'valuations'      => $m->valuations->map(fn ($v) => [
                        'date'  => $v->valued_at->toDateString(),
                        'value' => (float) $v->value,
                    ])->values(),
                ])->values(),
                'journal_entries'  => $p->journalEntries->map(fn ($j) => [
                    'date'  => $j->entry_date->toDateString(),
                    'title' => $j->title,
                    'body'  => $j->body,
                ])->values(),
            ])->values(),
            'liabilities'          => $liabilities->map(fn ($l) => [
                'name'            => $l->name,
                'liability_type'  => $l->liability_type,
                'interest_rate'   => $l->interest_rate !== null ? (float) $l->interest_rate : null,
                'minimum_payment' => $l->minimum_payment !== null ? (float) $l->minimum_payment : null,
                'currency'        => $l->currency,
                'notes'           => $l->notes,
                'balances'        => $l->balances->map(fn ($b) => [
                    'date'    => $b->recorded_at->toDateString(),
                    'balance' => (float) $b->balance,
                ])->values(),
            ])->values(),
            'cash_accounts'        => $cashAccounts->map(fn ($a) => [
                'name'         => $a->name,
                'account_type' => $a->account_type,
                'currency'     => $a->currency,
                'notes'        => $a->notes,
                'transactions' => $a->transactions->map(fn ($t) => [
                    'date'        => $t->occurred_at->toDateString(),
                    'type'        => $t->type,
                    'amount'      => (float) $t->amount,
                    'description' => $t->description,
                    'cleared'     => (bool) $t->cleared,
                ])->values(),
            ])->values(),
            'envelopes'            => $envelopes->map(fn ($e) => [
                'name'           => $e->name,
                'monthly_target' => $e->monthly_target !== null ? (float) $e->monthly_target : null,
                'goal_amount'    => $e->goal_amount !== null ? (float) $e->goal_amount : null,
                'goal_date'      => $e->goal_date?->toDateString(),
                'color'          => $e->color,
                'sort_order'     => $e->sort_order,
                'is_mandatory'   => (bool) $e->is_mandatory,
                'is_emergency_fund' => (bool) $e->is_emergency_fund,
                'is_savings'     => (bool) $e->is_savings,
                'notes'          => $e->notes,
                'transactions'   => $e->transactions->map(fn ($t) => [
                    'date'        => $t->occurred_at->toDateString(),
                    'type'        => $t->type,
                    'amount'      => (float) $t->amount,
                    'description' => $t->description,
                ])->values(),
            ])->values(),
            'income_entries'       => $user->incomeEntries()->orderBy('occurred_at')->get()->map(fn ($i) => [
                'date'        => $i->occurred_at->toDateString(),
                'amount'      => (float) $i->amount,
                'description' => $i->description,
            ])->values(),
            'scheduled_transactions' => $user->scheduledTransactions()->get()->map(fn ($s) => [
                'description'  => $s->description,
                'amount'       => (float) $s->amount,
                'type'         => $s->type,
                'recurrence'   => $s->recurrence,
                'next_due_at'  => $s->next_due_at?->toDateString(),
                'is_active'    => (bool) $s->is_active,
            ])->values(),
            'watchlist'            => $user->watchlistItems()->get()->map(fn ($w) => [
                'symbol'     => $w->symbol,
                'asset_type' => $w->asset_type,
                'notes'      => $w->notes,
            ])->values(),
        ];

        $filename = 'finance-backup-' . now()->format('Y-m-d') . '.json';
        $json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
