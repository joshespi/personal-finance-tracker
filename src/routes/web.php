<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AllTransactionsController;
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
    Route::patch('assets/{asset}/reclassify', [AssetController::class, 'reclassify'])->name('assets.reclassify');

    Route::resource('portfolios', PortfolioController::class);

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
