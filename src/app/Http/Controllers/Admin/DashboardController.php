<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $u = DB::table('users')->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admins,
             SUM(CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END) as unverified,
             SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_7d,
             SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_30d',
            [now()->subDays(7), now()->subDays(30)]
        )->first();

        $stats = [
            'total_users'        => (int) $u->total,
            'admin_count'        => (int) $u->admins,
            'unverified_count'   => (int) $u->unverified,
            'total_portfolios'   => Portfolio::count(),
            'total_transactions' => Transaction::count(),
            'new_users_7d'       => (int) $u->new_7d,
            'new_users_30d'      => (int) $u->new_30d,
        ];

        $recentUsers = User::latest()->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recentUsers'));
    }
}
