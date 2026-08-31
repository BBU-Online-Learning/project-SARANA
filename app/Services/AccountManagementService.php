<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountManagementService
{
    public function synchronized(Closure $callback): mixed
    {
        return $this->locked($callback);
    }

    public function assignableRoles(User $actor): Collection
    {
        return Role::query()->where('status', true)
            ->whereIn('name', Role::manageableNames($actor))->orderBy('name')->get();
    }

    public function save(User $actor, ?User $target, array $attributes): User
    {
        return $this->locked(function () use ($actor, $target, $attributes): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $target = $target ? User::query()->lockForUpdate()->findOrFail($target->id) : null;
            Gate::forUser($actor)->authorize($target ? 'update' : 'create', $target ?? User::class);

            if (! $this->assignableRoles($actor)->contains('id', $attributes['role_id'] ?? null)) {
                throw ValidationException::withMessages(['role_id' => 'You cannot assign this role.']);
            }

            $allowed = ['name', 'email', 'phone', 'role_id', 'status', 'profile'];
            if (! $target) {
                $allowed[] = 'password';
            }
            $attributes = array_intersect_key($attributes, array_flip($allowed));

            if ($target) {
                $newRole = Role::query()->findOrFail($attributes['role_id']);
                $changingRole = (int) $target->role_id !== (int) $newRole->id && $newRole->name !== Role::TEACHER;
                if ($changingRole || ($attributes['status'] ?? $target->status) !== 'active') {
                    $this->assertOwnershipAllowsAccountChange($target);
                }
            }

            if (($attributes['profile'] ?? null) instanceof UploadedFile) {
                $path = $attributes['profile']->store('images/users', 'public');
                $attributes['profile'] = Storage::disk('public')->url($path);
            } else {
                unset($attributes['profile']);
            }

            if ($target) {
                if (collect(['email', 'role_id', 'status'])->contains(fn ($key): bool => isset($attributes[$key]) && $attributes[$key] != $target->{$key})) {
                    \Illuminate\Support\Facades\Password::broker()->deleteToken($target);
                    $target->auth_version++;
                    $target->two_factor_recovery_token_hash = null;
                    $target->recovery_requested_by = null;
                    $target->setRememberToken(\Illuminate\Support\Str::random(60));
                }
                $target->update($attributes);

                return $target;
            }

            return User::query()->create($attributes);
        });
    }

    public function delete(User $actor, User $target): void
    {
        $this->withTarget($actor, $target, 'delete', function (User $target): void {
            $this->assertOwnershipAllowsAccountChange($target, includeArchived: true);
            $target->delete();
        });
    }

    public function provisionFirstSuperAdmin(int $userId, string $email, bool $commit): User
    {
        return $this->locked(function () use ($userId, $email, $commit): User {
            $role = Role::query()->where('name', Role::SUPER_ADMIN)->where('status', true)->first();
            if (! $role) {
                throw ValidationException::withMessages(['role' => 'The Super Admin role must be present and enabled.']);
            }

            $existing = User::withTrashed()->whereHas('role', function ($query): void {
                $query->withTrashed()->where('name', Role::SUPER_ADMIN);
            })->lockForUpdate()->get();

            if ($existing->isNotEmpty()) {
                throw ValidationException::withMessages(['user' => 'A Super Admin assignment already exists. This is not a recovery command.']);
            }

            $user = User::query()->lockForUpdate()->find($userId);
            if (! $user || $user->email !== $email || $user->status !== 'active'
                || ! $user->role?->status
                || ! in_array($user->role->name, [Role::ADMIN, Role::TEACHER, Role::STUDENT], true)) {
                throw ValidationException::withMessages(['user' => 'Select an existing active account with a fixed role and confirm its exact email.']);
            }

            $this->assertOwnershipAllowsAccountChange($user);
            if ($commit) {
                $user->update(['role_id' => $role->id]);
            }

            return $user;
        });
    }

    private function withTarget(User $actor, User $target, string $ability, Closure $callback): mixed
    {
        return $this->locked(function () use ($actor, $target, $ability, $callback): mixed {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            // Reauthorize after acquiring the mutex, never trust an earlier role snapshot.
            Gate::forUser($actor)->authorize($ability, $target);

            return $callback($target);
        });
    }

    private function assertOwnershipAllowsAccountChange(User $target, bool $includeArchived = false): void
    {
        $classes = $target->schoolClasses()->wherePivot('role', 'owner');
        if (! $includeArchived) {
            $classes->whereNull('school_classes.archived_at');
        }
        if ($classes->exists()) {
            throw ValidationException::withMessages([
                'user' => $includeArchived
                    ? 'Reassign class ownership before deleting this account, including archived classes.'
                    : 'Reassign ownership of all active classes before suspending or changing this owner to a non-Teacher role.',
            ]);
        }
    }

    private function locked(Closure $callback): mixed
    {
        $connection = User::resolveConnection();
        if ($connection->getDriverName() === 'mysql') {
            $engines = $connection->table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection->getDatabaseName())
                ->whereIn('TABLE_NAME', ['roles', 'users'])->pluck('ENGINE', 'TABLE_NAME');
            if ($engines->count() !== 2 || $engines->contains(fn ($engine): bool => strtolower($engine) !== 'innodb')) {
                throw ValidationException::withMessages(['role' => 'Account changes require InnoDB. Back up the database and run the fixed-role migration first.']);
            }
        }

        return User::resolveConnection()->transaction(function () use ($callback): mixed {
            // The fixed role is the mutex for all account mutations and provisioning.
            // A no-op write also serializes SQLite, where FOR UPDATE is unavailable.
            $anchor = Role::withTrashed()->where('name', Role::SUPER_ADMIN);
            $anchor->toBase()->update(['name' => Role::SUPER_ADMIN]);
            $roles = $anchor->lockForUpdate()->get();
            if ($roles->count() !== 1) {
                throw ValidationException::withMessages(['role' => 'Run the fixed-role migration and resolve ambiguous Super Admin roles first.']);
            }

            return $callback();
        }, 5);
    }
}
