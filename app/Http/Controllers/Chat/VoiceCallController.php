<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StartVoiceCallRequest;
use App\Http\Requests\Chat\VoiceCallActionRequest;
use App\Http\Requests\Chat\VoiceCallSignalRequest;
use App\Models\CallSession;
use App\Models\ChatRoom;
use App\Services\Chat\VoiceCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceCallController extends Controller
{
    public function __construct(
        private readonly VoiceCallService $voiceCallService,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $call = $this->voiceCallService->current($request->user());

        return response()->json([
            'call' => $call ? $this->voiceCallService->payload($call) : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StartVoiceCallRequest $request, ChatRoom $room): JsonResponse
    {
        $call = $this->voiceCallService->start($request->user(), $room, $request->validated('call_type', 'audio'), $request->validated('client_id'));

        return response()->json(['call' => $this->voiceCallService->payload($call)], 201);
    }

    public function accept(VoiceCallActionRequest $request, CallSession $call): JsonResponse
    {
        return $this->transition($request, $call, 'accept');
    }

    public function decline(VoiceCallActionRequest $request, CallSession $call): JsonResponse
    {
        return $this->transition($request, $call, 'decline');
    }

    public function cancel(VoiceCallActionRequest $request, CallSession $call): JsonResponse
    {
        return $this->transition($request, $call, 'cancel');
    }

    public function end(VoiceCallActionRequest $request, CallSession $call): JsonResponse
    {
        return $this->transition($request, $call, 'end');
    }

    public function timeout(VoiceCallActionRequest $request, CallSession $call): JsonResponse
    {
        return $this->transition($request, $call, 'timeout');
    }

    public function fail(VoiceCallActionRequest $request, CallSession $call): JsonResponse
    {
        return $this->transition($request, $call, 'fail');
    }

    public function signal(VoiceCallSignalRequest $request, CallSession $call): JsonResponse
    {
        $this->voiceCallService->signal(
            $request->user(),
            $call,
            $request->validated('type'),
            $request->validated('data'),
            $request->validated('client_id'),
        );

        return response()->json(['success' => true]);
    }

    private function transition(VoiceCallActionRequest $request, CallSession $call, string $action): JsonResponse
    {
        $call = $this->voiceCallService->transition($request->user(), $call, $action, $request->validated('client_id'));

        return response()->json(['call' => $this->voiceCallService->payload($call)]);
    }

    public function heartbeat(VoiceCallActionRequest $request, CallSession $call): JsonResponse
    {
        $call = $this->voiceCallService->heartbeat($request->user(), $call, $request->validated('client_id'));

        return response()->json(['call' => $this->voiceCallService->payload($call)])->header('Cache-Control', 'private, no-store');
    }

    public function ice(Request $request): JsonResponse
    {
        abort_unless(app(\App\Services\Chat\ChatAccessService::class)->ready($request->user()), 403);

        return response()->json(['ice_servers' => $this->voiceCallService->iceServers($request->user())])
            ->header('Cache-Control', 'private, no-store');
    }
}
