<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::withCount('portfolios')->latest()->paginate(50);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $user->loadCount('portfolios');
        $loginHistory = $user->loginHistory()->latest('created_at')->limit(10)->get();

        return view('admin.users.show', compact('user', 'loginHistory'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'is_admin' => ['boolean'],
        ]);

        $revokingAdmin = $user->is_admin && ! $request->boolean('is_admin');

        // Losing admin access has no in-app recovery path, so block the two ways a form
        // submit could leave zero admins: demoting yourself, or demoting the last one.
        if ($revokingAdmin && $user->id === $request->user()->id) {
            return back()->withErrors(['is_admin' => 'You cannot remove your own admin access.'])->withInput();
        }
        if ($revokingAdmin && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['is_admin' => 'Cannot remove the last remaining admin.'])->withInput();
        }

        // is_admin is deliberately not mass-assignable (see User::$fillable) — set explicitly.
        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);
        $user->is_admin = $request->boolean('is_admin');
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function verify(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            ActivityLog::record('user.verify', $user, ['target_name' => $user->name]);
        }

        return redirect()->route('admin.users.show', $user)->with('success', 'User marked as verified.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        ActivityLog::record('user.delete', $user, ['target_name' => $user->name, 'target_email' => $user->email]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
