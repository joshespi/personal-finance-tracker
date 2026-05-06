<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AllTransactionsController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\CashTransactionController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\ScheduledTransactionController;
use App\Http\Controllers\EnvelopeController;
use App\Http\Controllers\EnvelopeTransactionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LiabilityBalanceController;
use App\Http\Controllers\LiabilityController;
use App\Http\Controllers\TaxSummaryController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualAssetController;
use App\Http\Controllers\ManualValuationController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioTransferController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TickerSearchController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/about', fn () => view('about'))->middleware('auth')->name('about');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/transactions', AllTransactionsController::class)->name('transactions.all');
    Route::get('/tax', TaxSummaryController::class)->name('tax.summary');
    Route::get('/export/transactions', [ExportController::class, 'transactions'])->name('export.transactions');
    Route::get('/export/realized-gains', [ExportController::class, 'realizedGains'])->name('export.realized-gains');
    Route::patch('assets/{asset}/reclassify', [AssetController::class, 'reclassify'])->name('assets.reclassify');

    Route::resource('portfolios', PortfolioController::class);

    Route::prefix('portfolios/{portfolio}/journal')->name('portfolios.journal.')->group(function () {
        Route::get('/', [JournalEntryController::class, 'index'])->name('index');
        Route::post('/', [JournalEntryController::class, 'store'])->name('store');
        Route::get('/{entry}/edit', [JournalEntryController::class, 'edit'])->name('edit');
        Route::put('/{entry}', [JournalEntryController::class, 'update'])->name('update');
        Route::delete('/{entry}', [JournalEntryController::class, 'destroy'])->name('destroy');
    });

    Route::resource('portfolios.transactions', TransactionController::class)->shallow();

    Route::get('portfolios/{portfolio}/transactions/import/template', [TransactionImportController::class, 'template'])
        ->name('portfolios.transactions.import.template');

    Route::post('portfolios/{portfolio}/transactions/import', [TransactionImportController::class, 'store'])
        ->name('portfolios.transactions.import');

    Route::get('/transfers/create', [PortfolioTransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [PortfolioTransferController::class, 'store'])->name('transfers.store');

    Route::resource('portfolios.manual-assets', ManualAssetController::class)->shallow();

    Route::resource('manual-assets.valuations', ManualValuationController::class)
        ->shallow()
        ->only(['store', 'destroy']);

    Route::resource('liabilities', LiabilityController::class);

    Route::post('liabilities/{liability}/balances', [LiabilityBalanceController::class, 'store'])
        ->name('liabilities.balances.store');
    Route::delete('liability-balances/{balance}', [LiabilityBalanceController::class, 'destroy'])
        ->name('liabilities.balances.destroy');

    Route::resource('cash-accounts', CashAccountController::class);

    Route::post('cash-accounts/{cashAccount}/transactions', [CashTransactionController::class, 'store'])
        ->name('cash-accounts.transactions.store');
    Route::delete('cash-transactions/{transaction}', [CashTransactionController::class, 'destroy'])
        ->name('cash-accounts.transactions.destroy');

    Route::get('/cashflow', CashflowController::class)->name('cashflow');

    Route::resource('scheduled-transactions', ScheduledTransactionController::class);
    Route::patch('scheduled-transactions/{scheduledTransaction}/toggle', [ScheduledTransactionController::class, 'toggle'])
        ->name('scheduled-transactions.toggle');

    Route::resource('envelopes', EnvelopeController::class);

    Route::post('envelopes/{envelope}/transactions', [EnvelopeTransactionController::class, 'store'])
        ->name('envelopes.transactions.store');
    Route::delete('envelope-transactions/{transaction}', [EnvelopeTransactionController::class, 'destroy'])
        ->name('envelopes.transactions.destroy');

    // Watchlist
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::delete('/watchlist/{watchlistItem}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    // Ticker autocomplete
    Route::get('/tickers/search', TickerSearchController::class)->name('tickers.search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Stop impersonation — must be outside admin group so impersonated non-admin users can reach it
    Route::delete('admin/impersonate', [ImpersonationController::class, 'destroy'])->name('admin.impersonate.stop');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('users', AdminUserController::class)->only(['index', 'show', 'edit', 'update', 'destroy']);
        Route::get('activity', AdminActivityLogController::class)->name('activity');
        Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings');
        Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('impersonate/{user}', [ImpersonationController::class, 'store'])->name('impersonate');
    });
});

require __DIR__.'/auth.php';
