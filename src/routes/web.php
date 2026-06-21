<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\ToolsController as AdminToolsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AllocatorController;
use App\Http\Controllers\AllTransactionsController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BudgetRuleController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\CashTransactionController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtPayoffController;
use App\Http\Controllers\DividendController;
use App\Http\Controllers\EmergencyFundController;
use App\Http\Controllers\EnvelopeController;
use App\Http\Controllers\EnvelopeTransactionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\IncomeCategoryController;
use App\Http\Controllers\IncomeEntryController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LiabilityBalanceController;
use App\Http\Controllers\LiabilityController;
use App\Http\Controllers\ManualAssetController;
use App\Http\Controllers\ManualValuationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioSliceController;
use App\Http\Controllers\PortfolioTransferController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduledTransactionController;
use App\Http\Controllers\SpendingTrendsController;
use App\Http\Controllers\TaxSummaryController;
use App\Http\Controllers\TickerSearchController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Services\DemoMode;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/about', fn () => view('about'))->middleware('auth')->name('about');
Route::get('/donate', fn () => view('donate', ['btcAddress' => config('donate.btc_address')]))->middleware('auth')->name('donate');
Route::get('/offline', fn () => view('offline'))->name('offline');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::post('/demo-mode/toggle', function () {
        app(DemoMode::class)->toggle();

        return redirect()->back();
    })->name('demo-mode.toggle');

    Route::get('/transactions', AllTransactionsController::class)->name('transactions.all');
    Route::get('/dividends', DividendController::class)->name('dividends');
    Route::get('/tax', TaxSummaryController::class)->name('tax.summary');
    Route::get('/export', [ExportController::class, 'index'])->name('export.index');
    Route::get('/import/csv', [CsvImportController::class, 'index'])->name('import.csv');
    Route::post('/import/csv/upload', [CsvImportController::class, 'upload'])->name('import.csv.upload');
    Route::get('/import/csv/preview', [CsvImportController::class, 'preview'])->name('import.csv.preview');
    Route::post('/import/csv/commit', [CsvImportController::class, 'commit'])->name('import.csv.commit');
    Route::post('/import/csv/cancel', [CsvImportController::class, 'cancel'])->name('import.csv.cancel');
    Route::get('/export/transactions', [ExportController::class, 'transactions'])->name('export.transactions');
    Route::get('/export/realized-gains', [ExportController::class, 'realizedGains'])->name('export.realized-gains');
    Route::get('/export/backup', [ExportController::class, 'fullBackup'])->name('export.backup');
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

    Route::patch('portfolios/{portfolio}/chart-visibility', [PortfolioController::class, 'updateChartVisibility'])
        ->name('portfolios.chart-visibility');

    Route::patch('portfolios/{portfolio}/close', [PortfolioController::class, 'close'])->name('portfolios.close');
    Route::patch('portfolios/{portfolio}/reopen', [PortfolioController::class, 'reopen'])->name('portfolios.reopen');

    Route::post('portfolios/{portfolio}/slices', [PortfolioSliceController::class, 'store'])->name('portfolios.slices.store');
    Route::delete('portfolios/{portfolio}/slices/{slice}', [PortfolioSliceController::class, 'destroy'])->name('portfolios.slices.destroy');

    Route::resource('manual-assets.valuations', ManualValuationController::class)
        ->shallow()
        ->only(['store', 'destroy']);

    Route::resource('liabilities', LiabilityController::class);

    Route::post('liabilities/{liability}/balances', [LiabilityBalanceController::class, 'store'])
        ->name('liabilities.balances.store');
    Route::delete('liability-balances/{balance}', [LiabilityBalanceController::class, 'destroy'])
        ->name('liabilities.balances.destroy');

    // Literal route registered before the resource so "all" isn't matched as {cashAccount}.
    Route::get('cash-accounts/all', [CashAccountController::class, 'all'])->name('cash-accounts.all');

    Route::resource('cash-accounts', CashAccountController::class);

    Route::post('cash-accounts/{cashAccount}/reconcile', [CashAccountController::class, 'reconcile'])
        ->name('cash-accounts.reconcile');
    Route::post('cash-accounts/{cashAccount}/transactions', [CashTransactionController::class, 'store'])
        ->name('cash-accounts.transactions.store');
    Route::delete('cash-transactions/{transaction}', [CashTransactionController::class, 'destroy'])
        ->name('cash-accounts.transactions.destroy');

    Route::get('/analysis', AnalysisController::class)->name('analysis');
    Route::get('/planning', PlanningController::class)->name('planning');
    Route::get('/cashflow', CashflowController::class)->name('cashflow');
    Route::get('/spending-trends', SpendingTrendsController::class)->name('spending-trends');
    Route::get('/emergency-fund', EmergencyFundController::class)->name('emergency-fund');
    Route::get('/debt-payoff', DebtPayoffController::class)->name('debt-payoff');
    Route::get('/allocator', AllocatorController::class)->name('allocator');
    Route::get('/budget-rule', BudgetRuleController::class)->name('budget-rule');
    Route::get('/forecast', ForecastController::class)->name('forecast');
    Route::get('/ready-to-assign', fn () => redirect()->route('envelopes.index'))->name('ready-to-assign');
    Route::post('/income-entries', [IncomeEntryController::class, 'store'])->name('income-entries.store');
    Route::delete('/income-entries/{incomeEntry}', [IncomeEntryController::class, 'destroy'])->name('income-entries.destroy');

    Route::resource('income-categories', IncomeCategoryController::class)->except(['show']);

    Route::resource('scheduled-transactions', ScheduledTransactionController::class)->except(['index', 'show']);
    Route::patch('scheduled-transactions/{scheduledTransaction}/toggle', [ScheduledTransactionController::class, 'toggle'])
        ->name('scheduled-transactions.toggle');
    Route::post('scheduled-transactions/{scheduledTransaction}/enter-now', [ScheduledTransactionController::class, 'enterNow'])
        ->name('scheduled-transactions.enter-now');
    Route::post('scheduled-transactions/{scheduledTransaction}/skip', [ScheduledTransactionController::class, 'skip'])
        ->name('scheduled-transactions.skip');

    Route::post('envelopes/assign-one', [EnvelopeController::class, 'assignOne'])->name('envelopes.assign-one');
    Route::resource('envelopes', EnvelopeController::class);

    Route::post('envelopes/{envelope}/transactions', [EnvelopeTransactionController::class, 'store'])
        ->name('envelopes.transactions.store');
    Route::delete('envelope-transactions/{transaction}', [EnvelopeTransactionController::class, 'destroy'])
        ->name('envelopes.transactions.destroy');

    // Ticker autocomplete
    Route::get('/tickers/search', TickerSearchController::class)->name('tickers.search')->middleware('throttle:30,1');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/targets', [ProfileController::class, 'updateTargets'])->name('profile.targets');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Stop impersonation — must be outside admin group so impersonated non-admin users can reach it
    Route::delete('admin/impersonate', [ImpersonationController::class, 'destroy'])->name('admin.impersonate.stop');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('users', AdminUserController::class)->only(['index', 'show', 'edit', 'update', 'destroy']);
        Route::get('activity', AdminActivityLogController::class)->name('activity');
        Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings');
        Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/test-email', [AdminSettingsController::class, 'sendTestEmail'])->name('settings.test-email');
        Route::get('tools', [AdminToolsController::class, 'index'])->name('tools');
        Route::post('tools/backfill-snapshots', [AdminToolsController::class, 'backfillSnapshots'])->name('tools.backfill-snapshots');
        Route::post('impersonate/{user}', [ImpersonationController::class, 'store'])->name('impersonate');
    });
});

require __DIR__.'/auth.php';
