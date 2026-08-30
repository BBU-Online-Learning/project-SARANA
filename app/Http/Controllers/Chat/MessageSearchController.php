<?php

// app/Http/Controllers/Chat/MessageSearchController.php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MessageSearchController extends Controller
{



    /*
|--------------------------------------------------------------------------
| SEARCH MESSAGES INSIDE ONE ROOM
|--------------------------------------------------------------------------
| Returns matches in chronological order so the frontend can step
| forward/backward through them like Telegram's in-chat search.
*/
    public function searchInRoom(Request $request, ChatRoom $room)
    {
        $this->authorize('access', $room);

        $validated = $request->validate([
            'keyword' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $keyword = $validated['keyword'];
        $userId  = Auth::id();

        $messages = Message::query()
            ->where('room_id', $room->id)
            ->whereNull('deleted_for_everyone_at')
            ->whereDoesntHave('hiddenByUsers', fn($q) => $q->where('user_id', $userId))
            ->where('body', 'like', '%' . $keyword . '%')
            ->orderBy('created_at')
            ->limit(200)
            ->get(['id', 'body', 'created_at']);

        $results = $messages->map(fn($m) => [
            'message_id' => $m->id,
            'snippet'    => $this->buildSnippet($m->body, $keyword),
        ]);

        return response()->json([
            'count'   => $results->count(),
            'results' => $results,
        ]);
    }
    

    /*
    |--------------------------------------------------------------------------
    | BUILD SNIPPET
    |--------------------------------------------------------------------------
    | Centers a short excerpt of the message body around the first
    | occurrence of the keyword, so long messages don't dump their
    | entire body into the search results list.
    */
    private function buildSnippet(string $body, string $keyword, int $radius = 40): string
    {
        $position = mb_stripos($body, $keyword);

        if ($position === false) {
            return Str::limit($body, $radius * 2);
        }

        $start = max(0, $position - $radius);
        $length = mb_strlen($keyword) + ($radius * 2);

        $snippet = mb_substr($body, $start, $length);

        $prefix = $start > 0 ? '…' : '';
        $suffix = ($start + $length) < mb_strlen($body) ? '…' : '';

        return $prefix . $snippet . $suffix;
    }
}
