<?php

namespace App\Http\Controllers;

use Agence104\LiveKit\WebhookReceiver;
use App\Services\MeetingAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LiveKitWebhookController extends Controller
{
    public function __invoke(Request $request, MeetingAttendanceService $attendance): JsonResponse
    {
        abort_unless($request->isJson(), 415);
        $body = $request->getContent();
        abort_if(strlen($body) > 262144, 413);

        if (! filled(config('livekit.api_key')) || ! filled(config('livekit.api_secret'))) {
            return response()->json(['message' => 'LiveKit is not configured.'], 503);
        }

        try {
            $authorization = (string) $request->header('Authorization');
            if (! preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
                abort(401);
            }
            $event = (new WebhookReceiver(config('livekit.api_key'), config('livekit.api_secret')))
                ->receive($body, $matches[1]);
        } catch (Throwable) {
            return response()->json(['message' => 'Invalid LiveKit webhook.'], 401);
        }

        $attendance->record($event);

        return response()->json(['accepted' => true]);
    }
}
