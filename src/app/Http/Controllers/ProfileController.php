<?php

namespace App\Http\Controllers;

use App\Enums\DashboardWidget;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateTargets(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('investmentTargets', [
            'target_stock_pct'       => ['required', 'numeric', 'min:0', 'max:100'],
            'target_crypto_pct'      => ['required', 'numeric', 'min:0', 'max:100'],
            'target_real_estate_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'target_bond_pct'        => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $total = round(array_sum($data), 2);

        if ($total !== 0.0 && $total !== 100.0) {
            return back()->withErrors(['Percentages must sum to 100 (or all be 0 to disable).'], 'investmentTargets')
                ->withInput();
        }

        $request->user()->update($data);

        return Redirect::route('profile.edit')->with('status', 'targets-updated');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->update([
            'notify_scheduled_transactions' => $request->boolean('notify_scheduled_transactions'),
        ]);

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
    }

    public function updateDisplay(Request $request): RedirectResponse
    {
        // Checkboxes only submit the visible (checked) widgets; everything else is
        // hidden. Store an explicit true/false for every known widget so the map is
        // complete and self-documenting. Iterating the enum keys inherently ignores
        // any unknown submitted values.
        $submitted = array_flip((array) $request->input('widgets', []));

        $prefs = collect(DashboardWidget::values())
            ->mapWithKeys(fn (string $key) => [$key => isset($submitted[$key])])
            ->all();

        $request->user()->update(['dashboard_preferences' => $prefs]);

        return Redirect::route('profile.edit')->with('status', 'display-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
