<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuthSecurityService;
use App\Services\ClassAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return response()->view('profile.edit', ['user' => $request->user()])->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateProfileRequest $request, AuthSecurityService $security): RedirectResponse
    {
        $security->withUser($request->user()->id, function (User $user) use ($request): void {
            abort_unless($user->auth_version === $request->user()->auth_version
                && app(ClassAccessService::class)->ready($user), 403);
            $user->fill($request->safe()->only(['name', 'phone']));
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('images/users', 'public');
                abort_unless($path, 503, 'Photo storage is unavailable. Please try again.');
                $user->profile = Storage::disk('public')->url($path);
            }
            $user->save();
        });

        return redirect()->route('profile.edit')->with('success', 'Profile updated.');
    }
}
