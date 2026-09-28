<?php

namespace App\Services;

use App\Models\ClassMembershipAudit;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassManagementService
{
    public function __construct(private AccountManagementService $accounts, private ClassAccessService $access, private AcademicMembershipService $academicMemberships) {}

    public function synchronized(Closure $callback): mixed
    {
        $connection = SchoolClass::resolveConnection();
        if ($connection->getDriverName() === 'mysql') {
            $tables = [
                'school_classes', 'school_class_members', 'school_class_channels',
                'school_class_channel_messages', 'class_membership_audits',
                'academic_years', 'grade_levels', 'subjects', 'class_subjects',
                'student_class_enrollments', 'teacher_class_assignments', 'teacher_subject_assignments',
                'coursework_assignments', 'coursework_submissions', 'coursework_revisions',
                'coursework_attachments', 'coursework_grades',
                'class_attendance_registers', 'class_attendance_records', 'class_attendance_corrections',
                'class_announcements',
                'class_meetings', 'class_meeting_series', 'school_class_channel_reads',
            ];
            $engines = $connection->table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection->getDatabaseName())
                ->whereIn('TABLE_NAME', $tables)
                ->pluck('ENGINE');
            if ($engines->count() !== count($tables) || $engines->contains(fn (string $engine): bool => strtolower($engine) !== 'innodb')) {
                throw ValidationException::withMessages(['class' => 'Back up the database and run the class and academic migrations first.']);
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
        $added = $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($targetId, $role): bool {
            $target = User::query()->lockForUpdate()->findOrFail($targetId);
            Gate::forUser($actor)->authorize('addMember', [$schoolClass, $target, $role]);

            if ($this->access->membership($target, $schoolClass)) {
                return false;
            }

            $schoolClass->members()->attach($target, ['role' => $role, 'joined_at' => now()]);
            $this->audit($actor, $schoolClass, $target, 'member_added', null, $role);

            return true;
        });

        if ($added) {
            User::query()->find($targetId)?->notify(new ActivityNotification(
                'class',
                'Added to '.$schoolClass->name,
                'You can now open this class and its channels.',
                route('classes.show', $schoolClass, false),
            ));
        }

        return $added;
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

            abort_if($schoolClass->isArchived(), 403, 'Archived classes are read-only.');

            if (! $this->access->membership($actor, $schoolClass)) {
                $schoolClass->members()->attach($actor, ['role' => 'student', 'joined_at' => now()]);
                $this->audit($actor, $schoolClass, $actor,
                    $this->access->administrator($actor) ? 'administrative_enrollment' : 'joined', null, 'student');

                $ownerId = $schoolClass->memberRecords()->where('role', 'owner')->value('user_id');
                if ($ownerId && (int) $ownerId !== $actor->id) {
                    User::query()->find($ownerId)?->notify(new ActivityNotification(
                        'class',
                        'New class member',
                        $actor->name.' joined '.$schoolClass->name.'.',
                        route('classes.show', $schoolClass, false),
                    ));
                }
            }

            return $schoolClass;
        });
    }

    public function remove(User $actor, SchoolClass $schoolClass, User $target): ?string
    {
        $error = $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($target): ?string {
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

        if ($error === null) {
            $target->notify(new ActivityNotification(
                'class',
                'Removed from '.$schoolClass->name,
                'Your access to this class has ended.',
                route('classes.index', absolute: false),
            ));
        }

        return $error;
    }

    public function leave(User $actor, SchoolClass $schoolClass): ?string
    {
        return $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass): ?string {
            abort_if($schoolClass->isArchived(), 403, 'Archived classes are read-only.');
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
            $target->notify(new ActivityNotification(
                'class',
                'You now own '.$schoolClass->name,
                'You can manage members, channels, and quizzes.',
                route('classes.show', $schoolClass, false),
            ));
            $oldUser->notify(new ActivityNotification(
                'class',
                'Class ownership changed',
                $target->name.' now owns '.$schoolClass->name.'.',
                route('classes.show', $schoolClass, false),
            ));
        });
    }

    public function audit(User $actor, SchoolClass $schoolClass, User $target, string $action, ?string $oldRole, ?string $newRole): void
    {
        $this->academicMemberships->recordChange($schoolClass, $target, $oldRole, $newRole, $action);
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

    public function setArchived(User $actor, SchoolClass $schoolClass, bool $archived): void
    {
        $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($archived): void {
            Gate::forUser($actor)->authorize('manageLifecycle', $schoolClass);
            if ($schoolClass->isArchived() === $archived) {
                return;
            }
            if (! $archived) {
                $owners = $schoolClass->memberRecords()->where('role', 'owner')->with('user.role')->get();
                if ($owners->count() !== 1 || ! $owners->sole()->user || ! $this->access->eligibleTeacher($owners->sole()->user)) {
                    throw ValidationException::withMessages([
                        'class' => 'Restore requires exactly one active application Teacher owner. Administration must restore the owner account eligibility first.',
                    ]);
                }
            }
            $schoolClass->forceFill(['archived_at' => $archived ? now() : null])->save();
            $schoolClass->members()->get()->each(fn (User $member) => $member->notify(new ActivityNotification(
                'class',
                $archived ? 'Class archived' : 'Class restored',
                $schoolClass->name.($archived ? ' is now read-only.' : ' is available again.'),
                route('classes.show', $schoolClass, false),
            )));
        });
    }

    public function regenerateCode(User $actor, SchoolClass $schoolClass): void
    {
        $this->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass): void {
            Gate::forUser($actor)->authorize('regenerateCode', $schoolClass);
            $schoolClass->update(['join_code' => $this->joinCode()]);
        });
    }

    public function joinCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (SchoolClass::withTrashed()->where('join_code', $code)->exists());

        return $code;
    }
}
