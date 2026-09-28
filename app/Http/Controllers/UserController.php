<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\IndexUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private AccountManagementService $accounts) {}

    public function index(IndexUserRequest $request): View
    {
        Gate::authorize('viewAny', User::class);
        $names = Role::manageableNames($request->user());
        $search = $request->validated('search') ?? '';
        $role = $request->validated('role') ?? '';
        $status = $request->validated('status') ?? '';
        $users = User::query()->with('role')
            ->whereHas('role', fn ($query) => $query->whereIn('name', $names))
            ->when($role !== '', fn ($query) => $query->whereHas('role', fn ($roles) => $roles->where('name', $role)))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($accounts) => $accounts
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

        return view('users.index', compact('users', 'names', 'search', 'role', 'status'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);
        $roles = $this->accounts->assignableRoles($request->user());

        return view('users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->accounts->save($request->user(), null, $request->validated());

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user): RedirectResponse
    {
        Gate::authorize('view', $user);

        return redirect()->route('users.edit', $user);
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);
        $roles = $this->accounts->assignableRoles($request->user())->pluck('name', 'id');

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->accounts->save($request->user(), $user, $request->validated());

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->accounts->delete($request->user(), $user);

        return back()->with('success', 'User deleted successfully.');
    }

    public function generateTwoFactorQr(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        abort(410, 'Use verified email-assisted recovery instead.');
    }

    public function enableTwoFactor(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        abort(410, 'Only the account owner can confirm authenticator setup.');
    }

    public function disableTwoFactor(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        abort(410, 'Use verified email-assisted recovery instead.');
    }
}
