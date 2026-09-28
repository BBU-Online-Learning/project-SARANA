<?php

namespace App\Services;

use App\Models\ClassAnnouncement;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClassAnnouncementService
{
    public function __construct(private ClassManagementService $classes) {}

    /** @param array{title: string, body: string, expires_at?: string|null} $data */
    public function create(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, array $data): ClassAnnouncement
    {
        return $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $data): ClassAnnouncement {
            $channel = $this->lockedChannel($schoolClass, $channel);
            Gate::forUser($actor)->authorize('create', [ClassAnnouncement::class, $schoolClass, $channel]);

            return $channel->notices()->create([
                'author_id' => $actor->id, 'title' => $data['title'], 'body' => $data['body'],
                'expires_at' => $data['expires_at'] ?? null, 'status' => 'draft',
            ]);
        });
    }

    /** @param array{title: string, body: string, expires_at?: string|null} $data */
    public function update(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, array $data): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $notice, $data): void {
            $notice = $this->lockedNotice($schoolClass, $channel, $notice);
            Gate::forUser($actor)->authorize('update', $notice);
            $this->ensureExpiryAfter($data['expires_at'] ?? null, $notice->publish_at);
            $notice->update(['title' => $data['title'], 'body' => $data['body'], 'expires_at' => $data['expires_at'] ?? null]);
        });
    }

    public function schedule(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, string $publishAt): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $notice, $publishAt): void {
            $notice = $this->lockedNotice($schoolClass, $channel, $notice);
            Gate::forUser($actor)->authorize('update', $notice);
            if (Carbon::parse($publishAt, config('app.timezone'))->lte(now())) {
                throw ValidationException::withMessages(['publish_at' => 'Choose a future publication time.']);
            }
            $this->ensureExpiryAfter($notice->expires_at, $publishAt);
            $notice->update(['status' => 'scheduled', 'publish_at' => $publishAt, 'published_at' => null, 'scheduled_by' => $actor->id]);
        });
    }

    public function returnToDraft(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $notice): void {
            $notice = $this->lockedNotice($schoolClass, $channel, $notice);
            Gate::forUser($actor)->authorize('returnToDraft', $notice);
            $notice->update(['status' => 'draft', 'publish_at' => null, 'published_at' => null, 'scheduled_by' => null]);
        });
    }

    public function publish(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice): void
    {
        $notice = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $notice): ClassAnnouncement {
            $notice = $this->lockedNotice($schoolClass, $channel, $notice);
            Gate::forUser($actor)->authorize('publish', $notice);
            $this->ensureExpiryAfter($notice->expires_at, now());
            $notice->update(['status' => 'published', 'publish_at' => null, 'published_at' => now()]);

            return $notice;
        });

        $this->notifyPublished($notice);
    }

    public function pin(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, bool $pinned): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $notice, $pinned): void {
            $notice = $this->lockedNotice($schoolClass, $channel, $notice);
            Gate::forUser($actor)->authorize('pin', $notice);
            $notice->update(['pinned_at' => $pinned ? ($notice->pinned_at ?? now()) : null]);
        });
    }

    public function archive(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $notice): void {
            $notice = $this->lockedNotice($schoolClass, $channel, $notice);
            Gate::forUser($actor)->authorize('archive', $notice);
            $notice->update(['status' => 'archived', 'archived_at' => now(), 'archived_by' => $actor->id, 'pinned_at' => null]);
        });
    }

    public function restore(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice): void
    {
        $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $notice): void {
            $notice = $this->lockedNotice($schoolClass, $channel, $notice);
            Gate::forUser($actor)->authorize('restore', $notice);
            $notice->update([
                'status' => 'draft', 'archived_at' => null, 'archived_by' => null,
                'publish_at' => null, 'published_at' => null, 'scheduled_by' => null, 'pinned_at' => null,
            ]);
        });
    }

    /** @return array{published: int, expired: int, paused: int} */
    public function processDue(): array
    {
        $published = 0;
        $expired = 0;
        $paused = 0;
        ClassAnnouncement::query()->where('status', 'scheduled')->where('publish_at', '<=', now())
            ->chunkById(100, function ($notices) use (&$published, &$expired, &$paused): void {
                foreach ($notices as $notice) {
                    $result = $this->classes->synchronized(function () use ($notice): ?ClassAnnouncement {
                        $locked = ClassAnnouncement::query()->lockForUpdate()->findOrFail($notice->id);
                        if ($locked->status !== 'scheduled' || $locked->publish_at?->isFuture()) {
                            return null;
                        }
                        if ($locked->expires_at !== null && $locked->expires_at->lte(now())) {
                            $locked->update(['status' => 'expired', 'pinned_at' => null]);

                            return $locked;
                        }
                        $channel = $locked->channel;
                        $schoolClass = $channel?->schoolClass;
                        if (! $channel?->isAnnouncement() || ! $schoolClass) {
                            $locked->update(['status' => 'paused_unavailable']);

                            return $locked;
                        }
                        if ($schoolClass->isArchived()) {
                            $locked->update(['status' => 'paused_archived']);

                            return $locked;
                        }
                        $publisher = $locked->scheduled_by === null ? $locked->author : $locked->scheduledBy;
                        if (! $publisher || Gate::forUser($publisher)->denies('create', [ClassAnnouncement::class, $schoolClass, $channel])) {
                            $locked->update(['status' => 'paused_publisher']);

                            return $locked;
                        }
                        $locked->update(['status' => 'published', 'published_at' => now()]);

                        return $locked;
                    });
                    if ($result?->status === 'published') {
                        $published++;
                        $this->notifyPublished($result);
                    } elseif ($result?->status === 'expired') {
                        $expired++;
                    } elseif ($result !== null && in_array($result->status, ClassAnnouncement::PAUSED_STATUSES, true)) {
                        $paused++;
                    }
                }
            });

        ClassAnnouncement::query()->where('status', 'published')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->chunkById(100, function ($notices) use (&$expired): void {
                foreach ($notices as $notice) {
                    $changed = ClassAnnouncement::resolveConnection()->transaction(function () use ($notice): bool {
                        $locked = ClassAnnouncement::query()->lockForUpdate()->findOrFail($notice->id);
                        if ($locked->status !== 'published' || $locked->expires_at === null || $locked->expires_at->isFuture()) {
                            return false;
                        }
                        $locked->update(['status' => 'expired', 'pinned_at' => null]);

                        return true;
                    });
                    if ($changed) {
                        $expired++;
                    }
                }
            });

        return compact('published', 'expired', 'paused');
    }

    private function lockedChannel(SchoolClass $schoolClass, SchoolClassChannel $channel): SchoolClassChannel
    {
        return $schoolClass->channels()->lockForUpdate()->findOrFail($channel->id);
    }

    private function lockedNotice(SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice): ClassAnnouncement
    {
        return $this->lockedChannel($schoolClass, $channel)->notices()->lockForUpdate()->findOrFail($notice->id);
    }

    private function ensureExpiryAfter(string|CarbonInterface|null $expiresAt, string|CarbonInterface|null $publishAt): void
    {
        if ($expiresAt !== null && $publishAt !== null
            && Carbon::parse($expiresAt, config('app.timezone'))->lte(Carbon::parse($publishAt, config('app.timezone')))) {
            throw ValidationException::withMessages(['expires_at' => 'Expiry must be after publication.']);
        }
    }

    private function notifyPublished(ClassAnnouncement $notice): void
    {
        $notice->loadMissing('channel.schoolClass');
        $channel = $notice->channel;
        $schoolClass = $channel?->schoolClass;
        if (! $schoolClass) {
            return;
        }
        $schoolClass->members()->where('users.id', '!=', $notice->author_id)->get()
            ->each(fn (User $member) => $member->notify(new ActivityNotification(
                'class', 'New notice in '.$schoolClass->name,
                $notice->title,
                route('classes.channels.show', [$schoolClass, $channel], false),
            )));
    }
}
