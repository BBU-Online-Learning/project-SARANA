<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use App\Models\ClassMeeting;
use App\Models\User;

class LiveKitMeetingService
{
    public function configured(): bool
    {
        $url = config('livekit.url');
        $schemeValid = is_string($url) && (str_starts_with($url, 'wss://')
            || (app()->environment(['local', 'testing']) && str_starts_with($url, 'ws://')));

        return $schemeValid && filled(config('livekit.api_key')) && filled(config('livekit.api_secret'));
    }

    public function joinable(ClassMeeting $meeting): bool
    {
        return $this->configured() && $meeting->status === 'scheduled' && ! $meeting->schoolClass->isArchived()
            && now()->greaterThanOrEqualTo($meeting->starts_at->copy()->subMinutes(config('livekit.join_before_minutes')))
            && now()->lessThan($meeting->ends_at->copy()->addMinutes(config('livekit.join_after_minutes')));
    }

    /** @return array{url: string, token: string} */
    public function credentials(User $user, ClassMeeting $meeting): array
    {
        $closesAt = $meeting->ends_at->copy()->addMinutes(config('livekit.join_after_minutes'));
        $ttl = max(1, min(60, (int) now()->diffInSeconds($closesAt, false)));
        $token = new AccessToken(
            config('livekit.api_key'),
            config('livekit.api_secret'),
            (new AccessTokenOptions)
                ->setIdentity('user-'.$user->id)
                ->setName($user->name)
                ->setTtl($ttl),
        );
        $token->setGrant((new VideoGrant)
            ->setRoomJoin(true)
            ->setRoomName('class-'.$meeting->school_class_id.'-meeting-'.$meeting->id)
            ->setCanPublish(true)
            ->setCanSubscribe(true)
            ->setCanUpdateOwnMetadata(true));

        return ['url' => config('livekit.url'), 'token' => $token->toJwt()];
    }
}
