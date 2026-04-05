<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualAssetController;
use App\Http\Controllers\ManualValuationController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::resource('portfolios', PortfolioController::class);

    Route::resource('portfolios.transactions', TransactionController::class)->shallow();

    Route::get('portfolios/{portfolio}/transactions/import/template', [TransactionImportController::class, 'template'])
        ->name('portfolios.transactions.import.template');

    Route::post('portfolios/{portfolio}/transactions/import', [TransactionImportController::class, 'store'])
        ->name('portfolios.transactions.import');

    Route::resource('portfolios.manual-assets', ManualAssetController::class)->shallow();

    Route::resource('manual-assets.valuations', ManualValuationController::class)
        ->shallow()
        ->only(['store', 'destroy']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
