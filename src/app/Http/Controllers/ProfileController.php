<?php

namespace App\Http\Controllers;

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
            'target_stock_pct'       => ['required', 'integer', 'min:0', 'max:100'],
            'target_crypto_pct'      => ['required', 'integer', 'min:0', 'max:100'],
            'target_real_estate_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'target_bond_pct'        => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $total = array_sum($data);

        if ($total !== 0 && $total !== 100) {
            return back()->withErrors(['Percentages must sum to 100 (or all be 0 to disable).'], 'investmentTargets')
                ->withInput();
        }

        $request->user()->update($data);

        return Redirect::route('profile.edit')->with('status', 'targets-updated');
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
