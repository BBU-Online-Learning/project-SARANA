<?php

namespace App\Services;

use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Models\SchoolClassChannelRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class ClassChannelReadService
{
    public function mark(User $user, SchoolClassChannel $channel, int $messageId): SchoolClassChannelRead
    {
        $message = $channel->messages()->whereKey($messageId)->firstOrFail();

        return SchoolClassChannelRead::resolveConnection()->transaction(function () use ($user, $channel, $message): SchoolClassChannelRead {
            $state = SchoolClassChannelRead::query()->where('school_class_channel_id', $channel->id)
                ->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $state) {
                try {
                    $state = SchoolClassChannelRead::query()->create([
                        'school_class_channel_id' => $channel->id,
                        'user_id' => $user->id,
                    ]);
                } catch (QueryException) {
                    $state = SchoolClassChannelRead::query()->where('school_class_channel_id', $channel->id)
                        ->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                }
            }

            if ($message->id > $state->last_read_message_id) {
                $state->update(['last_read_message_id' => $message->id, 'last_read_at' => now()]);
            }

            return $state;
        });
    }

    /** @param Collection<int, SchoolClassChannel> $channels
     * @return array<int, int>
     */
    public function unreadCounts(User $user, Collection $channels): array
    {
        $channelIds = $channels->pluck('id')->all();
        if ($channelIds === []) {
            return [];
        }

        return SchoolClassChannelMessage::query()
            ->leftJoin('school_class_channel_reads as reads', function ($join) use ($user): void {
                $join->on('reads.school_class_channel_id', '=', 'school_class_channel_messages.school_class_channel_id')
                    ->where('reads.user_id', '=', $user->id);
            })
            ->whereIn('school_class_channel_messages.school_class_channel_id', $channelIds)
            ->where('school_class_channel_messages.sender_id', '!=', $user->id)
            ->where(function (Builder $query): void {
                $query->whereNull('reads.last_read_message_id')
                    ->orWhereColumn('school_class_channel_messages.id', '>', 'reads.last_read_message_id');
            })
            ->selectRaw('school_class_channel_messages.school_class_channel_id as channel_id, count(*) as unread_count')
            ->groupBy('school_class_channel_messages.school_class_channel_id')
            ->pluck('unread_count', 'channel_id')->map(fn ($count): int => (int) $count)->all();
    }
}
