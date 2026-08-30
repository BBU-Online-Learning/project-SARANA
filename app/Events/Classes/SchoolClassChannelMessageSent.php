<?php

namespace App\Events\Classes;

use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Services\ClassAccessService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SchoolClassChannelMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;

    protected int $channelId;

    public function __construct(SchoolClassChannelMessage $message)
    {
        $this->channelId = (int) $message->school_class_channel_id;
    }

    public function broadcastOn(): array
    {
        $channel = SchoolClassChannel::query()->with('schoolClass')->find($this->channelId);
        if (! $channel?->schoolClass) {
            return [];
        }

        $access = app(ClassAccessService::class);
        $topics = [];
        foreach ($channel->schoolClass->memberRecords()->with('user.role')->get() as $membership) {
            if ($membership->user && $access->ready($membership->user)
                && in_array($membership->role, ['owner', 'teacher', 'student'], true)) {
                $topics[] = new PrivateChannel('school-class.membership.'.$membership->id);
            }
        }

        return $topics;
    }

    public function broadcastAs(): string
    {
        return 'school-class.messages.changed';
    }

    public function broadcastWith(): array
    {
        // Even a signal already in flight before removal contains no class content.
        return [];
    }
}
