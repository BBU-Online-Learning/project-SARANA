<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\MarkMessagesReadRequest;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Services\Chat\ReadReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadReceiptController extends Controller
{
    public function store(MarkMessagesReadRequest $request, ChatRoom $room, ReadReceiptService $receipts): JsonResponse
    {
        return response()->json($receipts->mark($request->user(), $room, (int) $request->validated('up_to_message_id')));
    }

    public function show(Request $request, Message $message, ReadReceiptService $receipts): JsonResponse
    {
        $this->authorize('access', $message->room);
        abort_unless($message->sender_id === $request->user()->id, 403);
        abort_if($message->isDeletedForEveryone() || $message->isHiddenFor($request->user()->id), 404);

        return response()->json($receipts->details($message, $message->room))->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request, ChatRoom $room, ReadReceiptService $receipts): JsonResponse
    {
        $this->authorize('access', $room);
        $ids = array_slice(explode(',', (string) $request->query('ids', '')), 0, 50);
        $messages = $room->messages()->where('sender_id', $request->user()->id)->whereIn('id', $ids)
            ->whereNull('deleted_for_everyone_at')
            ->whereDoesntHave('hiddenByUsers', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('reads')->get();

        return response()->json(['statuses' => $messages->map(fn (Message $message): array => [
            'message_id' => $message->id, 'read_count' => $receipts->details($message, $room)['read_count'],
        ]), 'unread_count' => $receipts->unreadCount($room, $request->user()->id)])
            ->header('Cache-Control', 'private, no-store');
    }
}
