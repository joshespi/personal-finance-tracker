<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Cannot impersonate yourself.');

        ActivityLog::record('impersonate.start', $user, ['target_name' => $user->name]);

        $request->session()->put('impersonate_user_id', $user->id);
        $request->session()->put('impersonate_admin_id', $request->user()->id);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['impersonate_user_id', 'impersonate_admin_id']);

        ActivityLog::record('impersonate.stop');

        return redirect()->route('admin.users.index');
    }
}
