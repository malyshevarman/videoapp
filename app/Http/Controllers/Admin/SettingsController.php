<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $plainToken = null;
        $encryptedToken = AppSetting::getValue('external_api_bearer_token_encrypted');

        if (filled($encryptedToken)) {
            try {
                $plainToken = Crypt::decryptString($encryptedToken);
            } catch (\Throwable) {
                $plainToken = null;
            }
        }

        return view('admin.settings.index', [
            'tokenConfigured' => filled(AppSetting::getValue('external_api_bearer_token_hash')),
            'tokenUpdatedAt' => AppSetting::getValue('external_api_bearer_token_updated_at'),
            'plainToken' => $plainToken,
        ]);
    }

    public function regenerateToken(): RedirectResponse
    {
        $plainToken = Str::random(80);

        AppSetting::setValue('external_api_bearer_token_hash', Hash::make($plainToken));
        AppSetting::setValue('external_api_bearer_token_encrypted', Crypt::encryptString($plainToken));
        AppSetting::setValue('external_api_bearer_token_updated_at', now()->toISOString());

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Bearer token сгенерирован.');
    }
}
