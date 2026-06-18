<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = [
            'registration_open' => AppSetting::get('registration_open', '1'),
        ];

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        AppSetting::set('registration_open', $request->boolean('registration_open') ? '1' : '0');

        return redirect()->route('admin.settings')->with('success', 'Settings saved.');
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        Mail::raw(
            "Test email from Finance Tracker.\n\nSent to: {$user->email}\nMailer: ".config('mail.default'),
            fn ($m) => $m->to($user->email)->subject('Finance Tracker — test email')
        );

        return redirect()->route('admin.settings')->with('success', "Test email sent to {$user->email}.");
    }
}
