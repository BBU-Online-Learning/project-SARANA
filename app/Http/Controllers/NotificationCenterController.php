<?php

namespace App\Http\Controllers;

use App\Events\NotificationCenterChanged;
use App\Http\Requests\ReadRoomNotificationsRequest;
use App\Models\ChatRoom;
use App\Services\Chat\ChatAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);

        return response()->json([
            'notifications' => $notifications->getCollection()->map(fn ($notification): array => [
                'id' => $notification->id,
                'category' => $notification->data['category'] ?? 'general',
                'room_id' => $notification->data['room_id'] ?? null,
                'title' => $notification->data['title'] ?? 'Notification',
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? route('home', absolute: false),
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->diffForHumans(),
            ])->values(),
            'unread_count' => $user->unreadNotifications()->count(),
            'next_page' => $notifications->hasMorePages() ? $notifications->currentPage() + 1 : null,
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();
        $this->signalChange((int) $request->user()->id);

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->signalChange((int) $request->user()->id);

        return response()->json(['unread_count' => 0]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();
        $this->signalChange((int) $request->user()->id);

        return response()->json(['unread_count' => 0]);
    }

    public function readRoom(ReadRoomNotificationsRequest $request, ChatAccessService $access): JsonResponse
    {
        $roomId = (int) $request->validated('room_id');
        $room = ChatRoom::query()->findOrFail($roomId);
        abort_unless($access->access($request->user(), $room), 403);

        $changed = $request->user()->unreadNotifications()
            ->where(function ($query) use ($roomId): void {
                $query->where('data->room_id', $roomId)
                    ->orWhere('data->url', route('chat.index', ['room' => $roomId], false));
            })
            ->whereIn('data->category', ['message', 'call'])
            ->update(['read_at' => now()]);

        if ($changed > 0) {
            $this->signalChange((int) $request->user()->id);
        }

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    private function signalChange(int $userId): void
    {
        rescue(fn () => NotificationCenterChanged::dispatch($userId), report: true);
    }
}
