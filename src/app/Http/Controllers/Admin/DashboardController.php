<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'total_users'        => User::count(),
            'admin_count'        => User::where('is_admin', true)->count(),
            'unverified_count'   => User::whereNull('email_verified_at')->count(),
            'total_portfolios'   => Portfolio::count(),
            'total_transactions' => Transaction::count(),
            'new_users_7d'       => User::where('created_at', '>=', now()->subDays(7))->count(),
            'new_users_30d'      => User::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        $recentActivity = ActivityLog::with('user')->latest('created_at')->limit(15)->get();

        return view('admin.dashboard', compact('stats', 'recentActivity'));
    }
}
