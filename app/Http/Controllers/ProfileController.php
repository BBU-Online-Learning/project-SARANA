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
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('role');

        return response()->view('profile.edit', compact('user'))->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateProfileRequest $request, AuthSecurityService $security): RedirectResponse
    {
        $newProfilePath = null;
        try {
            $oldProfilePath = $security->withUser($request->user()->id, function (User $user) use ($request, &$newProfilePath): ?string {
                abort_unless($user->auth_version === $request->user()->auth_version
                    && app(ClassAccessService::class)->ready($user), 403);
                $oldProfilePath = $user->managedProfilePath();
                $user->fill($request->safe()->only(['name', 'phone', 'bio']));
                if ($request->hasFile('photo')) {
                    $newProfilePath = $request->file('photo')->store('images/users', 'public');
                    abort_unless($newProfilePath, 503, 'Photo storage is unavailable. Please try again.');
                    $user->profile = $newProfilePath;
                }
                $user->save();

                return $oldProfilePath;
            });
        } catch (Throwable $exception) {
            if ($newProfilePath) {
                Storage::disk('public')->delete($newProfilePath);
            }

            throw $exception;
        }
        if ($newProfilePath && $oldProfilePath && $oldProfilePath !== $newProfilePath) {
            Storage::disk('public')->delete($oldProfilePath);
        }

        $message = $newProfilePath
            ? 'Profile photo updated successfully.'
            : 'Profile updated successfully.';

        return redirect()->route('profile.edit')->with('success', $message);
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('viewProfile', $user);
        $user->load('role');
        $sharedClasses = $user->schoolClasses()->whereHas('members', function ($query) use ($request): void {
            $query->where('users.id', $request->user()->id);
        })->select('school_classes.id', 'school_classes.name')->orderBy('school_classes.name')->get();
        if ($request->user()->is($user)) {
            $sharedClasses = $user->schoolClasses()->select('school_classes.id', 'school_classes.name')
                ->orderBy('school_classes.name')->get();
        }

        return response()->view('profile.show', compact('user', 'sharedClasses'))->header('Cache-Control', 'private, no-store');
    }
}
