<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Actions\UpdateSettings;
use App\Modules\Settings\Support\CurrentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends \App\Http\Controllers\Controller
{
    public function show(CurrentSettings $currentSettings): Response
    {
        $setting = $currentSettings->get();

        return Inertia::render('Settings/Index', [
            'settings' => [
                'shop_name' => $setting->shop_name,
                'address' => $setting->address,
                'phone' => $setting->phone,
                'currency_symbol' => $setting->currency_symbol,
                'receipt_footer' => $setting->receipt_footer,
            ],
        ]);
    }

    public function update(Request $request, UpdateSettings $updateSettings): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ]);

        $updateSettings->handle($validated);

        return back()->with('success', 'Settings saved.');
    }
}
