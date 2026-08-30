<?php

namespace App\Http\Controllers;

use App\Http\Requests\Classes\JoinClassRequest;
use App\Http\Requests\Classes\StoreClassMemberRequest;
use App\Http\Requests\Classes\StoreClassRequest;
use App\Http\Requests\Classes\StoreSchoolClassChannelRequest;
use App\Http\Requests\Classes\TransferClassOwnershipRequest;
use App\Http\Requests\Classes\UpdateClassRequest;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\User;
use App\Services\ClassAccessService;
use App\Services\ClassManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolClassController extends Controller
{
    public function __construct(private ClassManagementService $classes, private ClassAccessService $access) {}

    public function index(): View
    {
        abort_unless($this->access->ready(Auth::user()), 403);
        $classes = SchoolClass::query()->with(['creator', 'channels'])->withCount('members')
            ->when(! $this->access->administrator(Auth::user()), function (Builder $query): void {
                $query->whereHas('members', fn (Builder $members): Builder => $members->where('users.id', Auth::id()));
            })->latest()->get();
        $eligibleTeachers = $this->access->administrator(Auth::user()) ? $this->eligibleTeachers()->get() : collect();

        return view('classes.index', compact('classes', 'eligibleTeachers'));
    }

    public function store(StoreClassRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $schoolClass = $this->classes->synchronized(function () use ($request, $validated): SchoolClass {
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            Gate::forUser($actor)->authorize('create', SchoolClass::class);
            $owner = $this->access->administrator($actor)
                ? User::query()->lockForUpdate()->findOrFail($validated['owner_id'])
                : $actor;
            if (! $this->access->eligibleTeacher($owner)) {
                throw ValidationException::withMessages(['owner_id' => 'Only an active application Teacher with an enabled role can own a class.']);
            }
            $schoolClass = SchoolClass::query()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'join_code' => $this->classes->joinCode(),
                'created_by' => $actor->id,
            ]);
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('class-avatars', 'local');
                abort_unless($path, 500);
                $schoolClass->update(['avatar' => $path]);
            }
            $schoolClass->members()->attach($owner, ['role' => 'owner', 'joined_at' => now()]);
            $this->classes->audit($actor, $schoolClass, $owner, 'owner_assigned', null, 'owner');
            foreach (['General', 'Discussion', 'Assignment', 'Announcement'] as $order => $name) {
                $schoolClass->channels()->create([
                    'name' => $name, 'slug' => Str::slug($name), 'sort_order' => $order + 1,
                    'created_by' => $actor->id, 'is_default' => true,
                ]);
            }

            return $schoolClass;
        });

        return redirect()->route('classes.show', $schoolClass)->with('success', 'Class created successfully.');
    }

    public function update(UpdateClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->classes->withClass($request->user(), $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($request): void {
            Gate::forUser($actor)->authorize('update', $schoolClass);
            $schoolClass->update($request->safe()->only(['name', 'description']));
        });

        return back()->with('success', 'Class information updated.');
    }

    public function avatar(SchoolClass $schoolClass): StreamedResponse
    {
        Gate::authorize('view', $schoolClass);
        $path = $schoolClass->avatar;
        abort_unless(is_string($path) && preg_match('~^class-avatars/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$~D', $path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'class-image.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function join(JoinClassRequest $request): RedirectResponse
    {
        $schoolClass = $this->classes->join($request->user(), $request->validated('join_code'));
        if (! $schoolClass) {
            return back()->with('error', 'Invalid join code.');
        }

        return redirect()->route('classes.show', $schoolClass)->with('success', 'You joined the class successfully.');
    }

    public function show(SchoolClass $schoolClass): View
    {
        Gate::authorize('view', $schoolClass);
        $schoolClass->load(['creator', 'members.role', 'channels']);
        $availableUsers = collect();
        if (Gate::allows('manageMembers', $schoolClass)) {
            $availableUsers = User::query()->with('role')->where('status', 'active')
                ->whereHas('role', function (Builder $query): void {
                    $roles = Auth::user()->role->name === Role::SUPER_ADMIN ? Role::NAMES : [Role::TEACHER, Role::STUDENT];
                    $query->where('status', true)->whereIn('name', $roles);
                })->whereDoesntHave('schoolClasses', fn (Builder $query): Builder => $query->where('school_classes.id', $schoolClass->id))
                ->orderBy('name')->get();
        }
        $eligibleTeachers = Gate::allows('transferOwnership', $schoolClass) ? $this->eligibleTeachers()->get() : collect();

        return view('classes.show', compact('schoolClass', 'availableUsers', 'eligibleTeachers'));
    }

    public function addChannel(StoreSchoolClassChannelRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->classes->withClass($request->user(), $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($request): void {
            Gate::forUser($actor)->authorize('manageChannels', $schoolClass);
            $validated = $request->validated();
            $baseSlug = Str::slug($validated['name']) ?: 'channel';
            $slug = $baseSlug;
            $suffix = 2;
            while ($schoolClass->channels()->withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }
            $schoolClass->channels()->create([
                'name' => $validated['name'], 'slug' => $slug, 'created_by' => $actor->id,
                'description' => $validated['description'] ?? null, 'is_default' => false,
                'sort_order' => (int) $schoolClass->channels()->withTrashed()->max('sort_order') + 1,
            ]);
        });

        return back()->with('success', 'Channel created successfully.');
    }

    public function removeChannel(SchoolClass $schoolClass, SchoolClassChannel $channel): RedirectResponse
    {
        $error = $this->classes->withClass(Auth::user(), $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel): ?string {
            Gate::forUser($actor)->authorize('manageChannels', $schoolClass);
            $channel = $schoolClass->channels()->lockForUpdate()->findOrFail($channel->id);
            if ($channel->is_default) {
                return 'Default channels cannot be deleted.';
            }
            $channel->delete();

            return null;
        });

        return $error ? back()->with('error', $error) : back()->with('success', 'Channel deleted successfully.');
    }

    public function addMember(StoreClassMemberRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $added = $this->classes->add($request->user(), $schoolClass, (int) $request->validated('user_id'), $request->validated('role'));

        return $added ? back()->with('success', 'Member added successfully.') : back()->with('error', 'This user is already a class member.');
    }

    public function removeMember(SchoolClass $schoolClass, User $user): RedirectResponse
    {
        $error = $this->classes->remove(Auth::user(), $schoolClass, $user);

        return $error ? back()->with('error', $error) : back()->with('success', 'Member removed successfully. The join code has been renewed.');
    }

    public function enroll(SchoolClass $schoolClass): RedirectResponse
    {
        $this->classes->enroll(Auth::user(), $schoolClass);

        return back()->with('success', 'Your administrative enrollment has been recorded.');
    }

    public function transferOwnership(TransferClassOwnershipRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->classes->transfer($request->user(), $schoolClass, (int) $request->validated('owner_id'));

        return back()->with('success', 'Ownership transferred. The previous owner remains a student member.');
    }

    public function leave(SchoolClass $schoolClass): RedirectResponse
    {
        $error = $this->classes->leave(Auth::user(), $schoolClass);

        return $error ? back()->with('error', $error) : redirect()->route('classes.index')->with('success', 'You left the class.');
    }

    private function eligibleTeachers(): Builder
    {
        return User::query()->where('status', 'active')
            ->whereHas('role', fn (Builder $query): Builder => $query->where('name', Role::TEACHER)->where('status', true))
            ->orderBy('name');
    }
}
