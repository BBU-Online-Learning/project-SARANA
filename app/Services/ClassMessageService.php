<?php

namespace App\Services;

use App\Events\Classes\SchoolClassChannelMessageSent;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClassMessageService
{
    public function __construct(private ClassManagementService $classes) {}

    public function send(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, array $data): SchoolClassChannelMessage
    {
        $message = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $data): SchoolClassChannelMessage {
            $channel = $schoolClass->channels()->lockForUpdate()->findOrFail($channel->id);
            Gate::forUser($actor)->authorize('sendMessage', [$schoolClass, $channel]);
            $uuid = $data['client_uuid'] ?? null;
            $existing = $uuid ? SchoolClassChannelMessage::withTrashed()->where('client_uuid', $uuid)->first() : null;
            if ($existing) {
                if ($existing->sender_id !== $actor->id || $existing->school_class_channel_id !== $channel->id
                    || $existing->trashed() || $existing->body !== $data['body']) {
                    throw ValidationException::withMessages(['client_uuid' => 'This send identifier has already been used. Refresh before sending a different message.']);
                }

                return $existing;
            }

            return $channel->messages()->create(['sender_id' => $actor->id, 'body' => $data['body'], 'client_uuid' => $uuid]);
        });
        if ($message->wasRecentlyCreated) {
            $this->signal($message);
        }

        return $message;
    }

    public function mutate(User $actor, SchoolClass $schoolClass, SchoolClassChannel $channel, int $messageId, ?string $body): SchoolClassChannelMessage
    {
        $message = $this->classes->withClass($actor, $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($channel, $messageId, $body): SchoolClassChannelMessage {
            $channel = $schoolClass->channels()->lockForUpdate()->findOrFail($channel->id);
            $message = $channel->messages()->withTrashed()->lockForUpdate()->findOrFail($messageId);
            Gate::forUser($actor)->authorize('manageOwnMessage', [$schoolClass, $channel, $message]);
            if ($body === null) {
                if (! $message->trashed()) {
                    $message->delete();
                }
            } else {
                abort_if($message->trashed(), 404);
                if ($message->body !== $body) {
                    $message->update(['body' => $body, 'is_edited' => true, 'edited_at' => now()]);
                }
            }

            return $message;
        });
        $this->signal($message);

        return $message;
    }

    private function signal(SchoolClassChannelMessage $message): void
    {
        try {
            event(new SchoolClassChannelMessageSent($message));
        } catch (Throwable) {
            Log::warning('Class message realtime signal failed; polling will recover.', [
                'channel_id' => $message->school_class_channel_id,
            ]);
        }
    }

    public function payload(SchoolClassChannelMessage $message, int $actorId, bool $canSend): array
    {
        $deleted = $message->trashed();

        return [
            'message_id' => $message->id,
            'sender_name' => ! $message->sender || $message->sender->trashed() ? 'Deleted user' : $message->sender->name,
            'body' => $deleted ? null : $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'is_edited' => $message->is_edited,
            'edited_at' => $message->edited_at?->toISOString(),
            'deleted' => $deleted,
            'can_modify' => ! $deleted && $canSend && $message->sender_id === $actorId,
        ];
    }
}
