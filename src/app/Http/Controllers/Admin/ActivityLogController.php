<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = ActivityLog::with('user')->latest('created_at');

        if ($userId = $request->integer('user_id', 0)) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        $logs    = $query->paginate(50)->withQueryString();
        $users   = User::orderBy('name')->get(['id', 'name']);
        $actions = ActivityLog::distinct()->orderBy('action')->pluck('action');

        return view('admin.activity.index', compact('logs', 'users', 'actions'));
    }
}
