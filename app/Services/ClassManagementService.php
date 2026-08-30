<?php

namespace App\Services;

use App\Models\ClassMembershipAudit;
use App\Models\SchoolClass;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassManagementService
{
    public function __construct(private AccountManagementService $accounts, private ClassAccessService $access) {}

    public function synchronized(Closure $callback): mixed
    {
        $connection = SchoolClass::resolveConnection();
        if ($connection->getDriverName() === 'mysql') {
            $engines = $connection->table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection->getDatabaseName())
                ->whereIn('TABLE_NAME', ['school_classes', 'school_class_members', 'school_class_channels', 'school_class_channel_messages', 'class_membership_audits'])
                ->pluck('ENGINE');
            if ($engines->count() !== 5 || $engines->contains(fn (string $engine): bool => strtolower($engine) !== 'innodb')) {
                throw ValidationException::withMessages(['class' => 'Back up the database and run the class authorization migration first.']);
            }
        }

        return $this->accounts->synchronized($callback);
    }

    public function withClass(User $actor, SchoolClass $schoolClass, Closure $callback): mixed
    {
        return $this->synchronized(function () use ($actor, $schoolClass, $callback): mixed {
            $version = (int) $actor->auth_version;
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $schoolClass = SchoolClass::query()->lockForUpdate()->findOrFail($schoolClass->id);
            abort_unless($this->access->ready($actor) && $actor->auth_version === $version, 403);

            return $callback($actor, $schoolClass);
        });
    }

    public function add(User $actor, SchoolClass $schoolClass, int $targetId, string $role): bool
    {
        return $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($targetId, $role): bool {
            $target = User::query()->lockForUpdate()->findOrFail($targetId);
            Gate::forUser($actor)->authorize('addMember', [$schoolClass, $target, $role]);

            if ($this->access->membership($target, $schoolClass)) {
                return false;
            }

            $schoolClass->members()->attach($target, ['role' => $role, 'joined_at' => now()]);
            $this->audit($actor, $schoolClass, $target, 'member_added', null, $role);

            return true;
        });
    }

    public function enroll(User $actor, SchoolClass $schoolClass): void
    {
        $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass): void {
            Gate::forUser($actor)->authorize('enroll', $schoolClass);
            $schoolClass->members()->attach($actor, ['role' => 'student', 'joined_at' => now()]);
            $this->audit($actor, $schoolClass, $actor, 'administrative_enrollment', null, 'student');
        });
    }

    public function join(User $actor, string $code): ?SchoolClass
    {
        return $this->synchronized(function () use ($actor, $code): ?SchoolClass {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($this->access->ready($actor), 403);
            $schoolClass = SchoolClass::query()->where('join_code', $code)->lockForUpdate()->first();
            if (! $schoolClass) {
                return null;
            }

            if (! $this->access->membership($actor, $schoolClass)) {
                $schoolClass->members()->attach($actor, ['role' => 'student', 'joined_at' => now()]);
                $this->audit($actor, $schoolClass, $actor,
                    $this->access->administrator($actor) ? 'administrative_enrollment' : 'joined', null, 'student');
            }

            return $schoolClass;
        });
    }

    public function remove(User $actor, SchoolClass $schoolClass, User $target): ?string
    {
        return $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($target): ?string {
            Gate::forUser($actor)->authorize('manageMembers', $schoolClass);
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $membership = $this->access->membership($target, $schoolClass);
            abort_unless($membership, 404);
            if ($membership->role === 'owner') {
                return 'The class owner cannot be removed.';
            }
            Gate::forUser($actor)->authorize('removeMember', [$schoolClass, $target]);
            $this->audit($actor, $schoolClass, $target, 'member_removed', $membership->role, null);
            $membership->delete();
            $schoolClass->update(['join_code' => $this->joinCode()]);

            return null;
        });
    }

    public function leave(User $actor, SchoolClass $schoolClass): ?string
    {
        return $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass): ?string {
            $membership = $this->access->membership($actor, $schoolClass);
            abort_unless($membership, 403);
            if ($membership->role === 'owner') {
                return 'Class owner cannot leave the class.';
            }
            $this->audit($actor, $schoolClass, $actor, 'left', $membership->role, null);
            $membership->delete();

            return null;
        });
    }

    public function transfer(User $actor, SchoolClass $schoolClass, int $ownerId): void
    {
        $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($ownerId): void {
            Gate::forUser($actor)->authorize('transferOwnership', $schoolClass);
            $target = User::query()->lockForUpdate()->findOrFail($ownerId);
            if (! $this->access->eligibleTeacher($target)) {
                throw ValidationException::withMessages(['owner_id' => 'The owner must be an active application Teacher with an enabled role.']);
            }
            $owners = $schoolClass->memberRecords()->where('role', 'owner')->get();
            if ($owners->count() !== 1) {
                throw ValidationException::withMessages(['owner_id' => 'This class must have exactly one current owner. Review its membership records first.']);
            }
            $oldOwner = $owners->sole();
            if ((int) $oldOwner->user_id === $ownerId) {
                throw ValidationException::withMessages(['owner_id' => 'Choose a different owner.']);
            }
            $oldUser = User::withTrashed()->findOrFail($oldOwner->user_id);
            $oldOwner->update(['role' => 'student']);
            $this->audit($actor, $schoolClass, $oldUser, 'ownership_transferred_out', 'owner', 'student');

            $membership = $this->access->membership($target, $schoolClass);
            $oldRole = $membership?->role;
            if ($membership) {
                $membership->update(['role' => 'owner']);
            } else {
                $schoolClass->members()->attach($target, ['role' => 'owner', 'joined_at' => now()]);
            }
            $this->audit($actor, $schoolClass, $target, 'ownership_transferred_in', $oldRole, 'owner');
        });
    }

    public function audit(User $actor, SchoolClass $schoolClass, User $target, string $action, ?string $oldRole, ?string $newRole): void
    {
        ClassMembershipAudit::query()->create([
            'school_class_id' => $schoolClass->id,
            'actor_id' => $actor->id,
            'target_id' => $target->id,
            'actor_role' => $actor->role->name,
            'action' => $action,
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ]);
    }

    public function joinCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (SchoolClass::withTrashed()->where('join_code', $code)->exists());

        return $code;
    }
}
