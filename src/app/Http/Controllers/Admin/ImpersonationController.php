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
        abort_if($user->is_admin, 403, 'Cannot impersonate another admin.');

        ActivityLog::record('impersonate.start', $user, ['target_name' => $user->name]);

        $request->session()->put('impersonate_user_id', $user->id);
        $request->session()->put('impersonate_admin_id', $request->user()->id);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Capture admin ID before clearing — HandleImpersonation already swapped Auth to the
        // impersonated user for this request, so auth()->id() would record the wrong actor.
        $adminId = $request->session()->get('impersonate_admin_id');

        $request->session()->forget(['impersonate_user_id', 'impersonate_admin_id']);

        ActivityLog::create([
            'user_id'    => $adminId,
            'action'     => 'impersonate.stop',
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
        ]);

        return redirect()->route('admin.users.index');
    }
}
