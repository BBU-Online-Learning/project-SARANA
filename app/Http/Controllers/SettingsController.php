<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateApplicationSettingsRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\AppSetting;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('role');
        $defaults = AppSetting::defaults();
        $preferences = array_replace($defaults, array_intersect_key($user->preferences ?? [], AppSetting::DEFAULT_PREFERENCES));

        return response()->view('settings.edit', compact('user', 'preferences', 'defaults'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $preferences = AppSetting::normalize($request->validated());
        $request->user()->forceFill(['preferences' => $preferences])->save();

        return redirect()->route('settings.edit')->with('success', 'Your settings were saved.');
    }

    public function updateApplication(UpdateApplicationSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->role?->name === Role::SUPER_ADMIN, 403);

        $defaults = AppSetting::normalize($request->validated('defaults'));
        AppSetting::query()->updateOrCreate(
            ['key' => 'user_defaults'],
            ['value' => $defaults],
        );

        return redirect()->route('settings.edit')->with('success', 'Application defaults were saved.');
    }
}
