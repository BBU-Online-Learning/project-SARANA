<?php

namespace App\Services;

use App\Models\ClassAnnouncement;
use App\Models\ClassMeeting;
use App\Models\CourseworkAssignment;
use App\Models\Message;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannelMessage;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class GlobalSearchService
{
    public const LIMIT = 10;

    public const CATEGORIES = ['classes', 'people', 'quizzes', 'coursework', 'meetings', 'announcements', 'chat'];

    /** @return array<string, LengthAwarePaginator> */
    public function search(User $viewer, string $term, string $activeCategory = 'classes', int $page = 1): array
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $viewerId = $viewer->id;
        $teacher = $viewer->role?->name === Role::TEACHER;
        $administrator = in_array($viewer->role?->name, [Role::ADMIN, Role::SUPER_ADMIN], true);
        $pageFor = fn (string $category): int => $activeCategory === $category ? $page : 1;

        $classes = SchoolClass::query();
        if (! $administrator) {
            $classes->whereHas('memberRecords', fn (Builder $query) => $query->where('user_id', $viewerId));
        }

        $people = User::query()->where('status', 'active')->where('google2fa_enabled', true)
            ->where('must_change_password', false)->whereHas('role', fn (Builder $query) => $query->where('status', true));
        $people->where(function (Builder $query) use ($viewer, $viewerId): void {
            $query->whereKey($viewerId)
                ->orWhereHas('schoolClasses', fn (Builder $class) => $class->whereHas('members', fn (Builder $member) => $member->whereKey($viewerId)))
                ->orWhereHas('chatRooms', fn (Builder $room) => $room->whereHas('members', fn (Builder $member) => $member->whereKey($viewerId)));
            $manageable = Role::manageableNames($viewer);
            if ($manageable !== []) {
                $query->orWhereHas('role', fn (Builder $role) => $role->whereIn('name', $manageable));
            }
        });

        $quizzes = Quiz::query()->whereHas('schoolClass')->where(function (Builder $query) use ($viewerId, $teacher): void {
            if ($teacher) {
                $query->whereHas('schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId)->whereIn('role', ['owner', 'teacher']));
            }
            $query->orWhere(function (Builder $student) use ($viewerId): void {
                $student->where('status', 'published')
                    ->whereHas('schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId)->where('role', 'student'))
                    ->whereHas('assignments', fn (Builder $assignment) => $assignment->whereHas('students', fn (Builder $user) => $user->whereKey($viewerId)));
            });
        });

        $coursework = CourseworkAssignment::query()->whereHas('schoolClass')->where(function (Builder $query) use ($viewerId, $teacher): void {
            if ($teacher) {
                $query->whereHas('schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId)->whereIn('role', ['owner', 'teacher']));
            }
            $query->orWhere(function (Builder $student) use ($viewerId): void {
                $student->whereIn('status', ['published', 'closed'])
                    ->whereHas('schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId)->where('role', 'student'));
            });
        });

        $meetings = ClassMeeting::query()->whereHas('schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId)->whereIn('role', ['owner', 'teacher', 'student']));

        $notices = ClassAnnouncement::query()->whereHas('channel.schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId))
            ->whereHas('channel', fn (Builder $channel) => $channel->where('slug', 'announcement')->where('is_default', true));
        if (! $teacher) {
            $notices->visible();
        } else {
            $notices->where(function (Builder $query) use ($viewerId): void {
                $query->visible()->orWhereHas('channel.schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId)->whereIn('role', ['owner', 'teacher']));
            });
        }

        $channelMessages = SchoolClassChannelMessage::query()->whereHas('channel.schoolClass.memberRecords', fn (Builder $member) => $member->where('user_id', $viewerId));
        $legacyNotices = (clone $channelMessages)->whereHas('channel', fn (Builder $channel) => $channel->where('slug', 'announcement')->where('is_default', true));
        $classMessages = (clone $channelMessages)->whereHas('channel', fn (Builder $channel) => $channel->where(function (Builder $query): void {
            $query->where('slug', '!=', 'announcement')->orWhere('is_default', false);
        }));
        $messages = Message::query()->where('message_type', 'text')->whereNull('deleted_for_everyone_at')->whereDoesntHave('attachments')
            ->whereDoesntHave('hiddenByUsers', fn (Builder $hidden) => $hidden->where('user_id', $viewerId))
            ->whereHas('room.roomMembers', fn (Builder $member) => $member->where('user_id', $viewerId));

        return [
            'classes' => $this->page($this->matching($classes, ['name', 'description'], $pattern)->orderBy('name')->orderBy('id'),
                fn (SchoolClass $class): array => $this->result($class->name, 'Class', route('classes.show', $class)), $pageFor('classes')),
            'people' => $this->page($this->matching($people, ['name'], $pattern)->orderBy('name')->orderBy('id'),
                fn (User $person): array => $this->result($person->name, 'Person', route('users.profile', $person)), $pageFor('people')),
            'quizzes' => $this->page($this->matching($quizzes, ['title', 'description'], $pattern)->with('schoolClass')->orderByDesc('id'),
                fn (Quiz $quiz): array => $this->result($quiz->title, $quiz->schoolClass->name.' · Quiz', route('classes.assessments.index', $quiz->schoolClass)), $pageFor('quizzes')),
            'coursework' => $this->page($this->matching($coursework, ['title', 'instructions'], $pattern)->with('schoolClass')->orderByDesc('id'),
                fn (CourseworkAssignment $assignment): array => $this->result($assignment->title, $assignment->schoolClass->name.' · Coursework', route('classes.coursework.assignments.show', [$assignment->schoolClass, $assignment])), $pageFor('coursework')),
            'meetings' => $this->page($this->matching($meetings, ['title', 'description'], $pattern)->with('schoolClass')->orderByDesc('starts_at')->orderByDesc('id'),
                fn (ClassMeeting $meeting): array => $this->result($meeting->title, $meeting->schoolClass->name.' · '.ucfirst($meeting->status).' meeting', route('classes.meetings.show', [$meeting->schoolClass, $meeting])), $pageFor('meetings')),
            'announcements' => $this->combine(
                $this->matching($notices, ['title', 'body'], $pattern),
                $this->matching($legacyNotices, ['body'], $pattern),
                'notice', 'legacy_notice', 'channel', 'channel',
                fn (ClassAnnouncement $notice): array => $this->result($notice->title, 'Notice · '.Str::limit($notice->body, 100),
                    route('classes.channels.show', [$notice->channel->school_class_id, $notice->channel, 'notice_id' => $notice->id]).'#notice-'.$notice->id),
                fn (SchoolClassChannelMessage $message): array => $this->result(Str::limit($message->body, 90), 'Announcement message',
                    route('classes.channels.show', [$message->channel->school_class_id, $message->channel, 'message_id' => $message->id]).'#class-message-'.$message->id),
                $pageFor('announcements')),
            'chat' => $this->combine(
                $this->matching($messages, ['body'], $pattern),
                $this->matching($classMessages, ['body'], $pattern),
                'room_message', 'class_message', 'room', 'channel',
                fn (Message $message): array => $this->result(Str::limit($message->body, 90), 'Chat message', route('chat.index', ['room' => $message->room_id])),
                fn (SchoolClassChannelMessage $message): array => $this->result(Str::limit($message->body, 90), 'Class channel message',
                    route('classes.channels.show', [$message->channel->school_class_id, $message->channel, 'message_id' => $message->id]).'#class-message-'.$message->id),
                $pageFor('chat')),
        ];
    }

    /** @param list<string> $columns */
    private function matching(Builder $query, array $columns, string $pattern): Builder
    {
        return $query->where(function (Builder $query) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $query->orWhereRaw("{$column} LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }

    private function page(Builder $query, Closure $present, int $page): LengthAwarePaginator
    {
        $results = $query->paginate(self::LIMIT, ['*'], 'page', $page);
        $results->setCollection($results->getCollection()->map($present));

        return $results;
    }

    private function combine(
        Builder $first,
        Builder $second,
        string $firstSource,
        string $secondSource,
        string $firstRelation,
        string $secondRelation,
        Closure $firstResult,
        Closure $secondResult,
        int $page,
    ): LengthAwarePaginator {
        $firstRows = (clone $first)->selectRaw("'{$firstSource}' as source, id, created_at")->toBase();
        $secondRows = (clone $second)->selectRaw("'{$secondSource}' as source, id, created_at")->toBase();
        $results = $first->getModel()->getConnection()->query()->fromSub($firstRows->unionAll($secondRows), 'search_matches')
            ->orderByDesc('created_at')->orderBy('source')->orderByDesc('id')
            ->paginate(self::LIMIT, ['*'], 'page', $page);
        $firstIds = $results->getCollection()->where('source', $firstSource)->pluck('id')->all();
        $secondIds = $results->getCollection()->where('source', $secondSource)->pluck('id')->all();
        $firstModels = (clone $first)->whereIn('id', $firstIds)->with($firstRelation)->get()->keyBy('id');
        $secondModels = (clone $second)->whereIn('id', $secondIds)->with($secondRelation)->get()->keyBy('id');

        $results->setCollection($results->getCollection()->map(function (object $row) use (
            $firstSource, $firstModels, $secondModels, $firstResult, $secondResult
        ): ?array {
            $record = $row->source === $firstSource ? $firstModels->get($row->id) : $secondModels->get($row->id);
            if (! $record instanceof Model) {
                return null;
            }

            return $row->source === $firstSource ? $firstResult($record) : $secondResult($record);
        })->filter()->values());

        return $results;
    }

    /** @return array{title: string, detail: string, url: string} */
    private function result(string $title, string $detail, string $url): array
    {
        return compact('title', 'detail', 'url');
    }
}
