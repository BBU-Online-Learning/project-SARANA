<?php
// app/Http/Controllers/Chat/MessageReactionController.php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\MessageReactionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageReactionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TOGGLE REACTION  (add if absent, remove if present)
    |--------------------------------------------------------------------------
    */

    public function toggle(Request $request, Message $message)
    {
        $this->authorize('react', $message);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'in:' . implode(',', config('chat.allowed_reactions'))],
        ]);

        $userId = Auth::id();
        // $emoji  = $validated['emoji'];
        // Normalize to NFC so visually-identical emoji always store as identical bytes
        $emoji = \Normalizer::normalize($validated['emoji'], \Normalizer::FORM_C);

        $existing = MessageReaction::where([
            'message_id' => $message->id,
            'user_id'    => $userId,
        ])->first();

        if ($existing && $existing->emoji === $emoji) {
            // Clicking the same emoji again — remove it (toggle off)
            $existing->delete();
        } elseif ($existing) {
            // Switching to a different emoji — replace it
            $existing->update(['emoji' => $emoji]);
        } else {
            // No existing reaction — create one
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id'    => $userId,
                'emoji'      => $emoji,
            ]);
        }


        $reactionCounts = $message->reactionCounts();

        broadcast(
            new MessageReactionUpdated(
                $message->id,
                $message->room_id,
                $reactionCounts
            )
        );

        return response()->json(['success' => true]);
    }
}
