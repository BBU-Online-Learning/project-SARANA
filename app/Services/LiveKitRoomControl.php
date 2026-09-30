<?php

namespace App\Services;

use Agence104\LiveKit\RoomServiceClient;
use App\Models\ClassMeeting;
use RuntimeException;
use Twirp\Error;
use Twirp\ErrorCode;

class LiveKitRoomControl
{
    public function __construct(private LiveKitMeetingService $liveKit) {}

    public function end(ClassMeeting $meeting): void
    {
        $roomName = $this->liveKit->roomName($meeting);
        $client = $this->client();
        if (count($client->listRooms([$roomName])->getRooms()) > 0) {
            $client->deleteRoom($roomName);
        }
    }

    public function removeParticipant(ClassMeeting $meeting, int $userId): void
    {
        $roomName = $this->liveKit->roomName($meeting);
        try {
            $this->client()->removeParticipant($roomName, 'user-'.$userId);
        } catch (Error $error) {
            if ($error->getErrorCode() !== ErrorCode::NotFound) {
                throw $error;
            }
        }
    }

    private function client(): RoomServiceClient
    {
        if (! $this->liveKit->configured()) {
            throw new RuntimeException('LiveKit is not configured.');
        }

        $url = (string) config('livekit.url');
        $host = str_starts_with($url, 'wss://') ? 'https://'.substr($url, 6) : 'http://'.substr($url, 5);

        return new RoomServiceClient($host, config('livekit.api_key'), config('livekit.api_secret'));
    }
}
