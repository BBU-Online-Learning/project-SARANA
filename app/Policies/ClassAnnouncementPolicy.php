<?php

namespace App\Policies;

use App\Models\ClassAnnouncement;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\User;
use App\Services\ClassAccessService;

class ClassAnnouncementPolicy
{
    public function __construct(private ClassAccessService $access) {}

    public function viewAny(User $user, SchoolClass $schoolClass, SchoolClassChannel $channel): bool
    {
        return $this->inAnnouncementChannel($schoolClass, $channel) && $this->access->content($user, $schoolClass);
    }

    public function view(User $user, ClassAnnouncement $notice): bool
    {
        $channel = $notice->channel;
        if (! $channel || ! $this->viewAny($user, $channel->schoolClass, $channel)) {
            return false;
        }

        return $this->access->teachingRole($user, $channel->schoolClass) !== null || $notice->isVisible();
    }

    public function create(User $user, SchoolClass $schoolClass, SchoolClassChannel $channel): bool
    {
        return $this->inAnnouncementChannel($schoolClass, $channel)
            && ! $schoolClass->isArchived()
            && $this->access->teachingRole($user, $schoolClass) !== null;
    }

    public function update(User $user, ClassAnnouncement $notice): bool
    {
        return in_array($notice->status, ['draft', 'scheduled', ...ClassAnnouncement::PAUSED_STATUSES], true)
            && $this->canManage($user, $notice);
    }

    public function publish(User $user, ClassAnnouncement $notice): bool
    {
        return $this->update($user, $notice);
    }

    public function returnToDraft(User $user, ClassAnnouncement $notice): bool
    {
        return in_array($notice->status, ['scheduled', ...ClassAnnouncement::PAUSED_STATUSES], true)
            && $this->canManage($user, $notice);
    }

    public function pin(User $user, ClassAnnouncement $notice): bool
    {
        return $notice->status === 'published' && $notice->published_at?->lte(now())
            && ($notice->expires_at === null || $notice->expires_at->isFuture())
            && $this->canManage($user, $notice);
    }

    public function archive(User $user, ClassAnnouncement $notice): bool
    {
        return $notice->status !== 'archived' && $this->canManage($user, $notice);
    }

    public function restore(User $user, ClassAnnouncement $notice): bool
    {
        return $notice->status === 'archived' && $this->canManage($user, $notice);
    }

    private function canManage(User $user, ClassAnnouncement $notice): bool
    {
        $channel = $notice->channel;

        return $channel && $this->create($user, $channel->schoolClass, $channel);
    }

    private function inAnnouncementChannel(SchoolClass $schoolClass, SchoolClassChannel $channel): bool
    {
        return (int) $channel->school_class_id === (int) $schoolClass->id
            && ! $channel->trashed() && $channel->isAnnouncement();
    }
}
