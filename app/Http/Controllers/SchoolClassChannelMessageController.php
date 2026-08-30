<?php

namespace App\Http\Controllers;

use App\Events\Classes\SchoolClassChannelMessageSent;
use App\Http\Requests\Classes\ClassMessagesRequest;
use App\Http\Requests\Classes\StoreSchoolClassChannelMessageRequest;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Models\User;
use App\Services\ClassAccessService;
use App\Services\ClassManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SchoolClassChannelMessageController extends Controller
{
    public function show(SchoolClass $schoolClass, SchoolClassChannel $channel): Response
    {
        Gate::authorize('viewChannel', [$schoolClass, $channel]);
        $schoolClass->load(['creator', 'channels']);
        $messages = $channel->messages()->with('sender')->orderBy('id')->get();
        $membership = app(ClassAccessService::class)->membership(Auth::user(), $schoolClass);

        return response()->view('classes.channels.show', compact('schoolClass', 'channel', 'messages', 'membership'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function index(ClassMessagesRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel): JsonResponse
    {
        Gate::authorize('viewChannel', [$schoolClass, $channel]);
        $after = (int) $request->validated('after_id', 0);
        $messages = $channel->messages()->with('sender')->where('id', '>', $after)->orderBy('id')->limit(100)->get();

        return response()->json([
            'messages' => $messages->map(fn (SchoolClassChannelMessage $message): array => [
                'message_id' => $message->id,
                'sender_name' => $message->sender?->name ?? 'Deleted user',
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
            ]),
            'next_id' => $messages->last()?->id ?? $after,
            'has_more' => $messages->count() === 100,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreSchoolClassChannelMessageRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel): RedirectResponse
    {
        $message = app(ClassManagementService::class)->withClass($request->user(), $schoolClass,
            function (User $actor, SchoolClass $schoolClass) use ($channel, $request): SchoolClassChannelMessage {
                $channel = $schoolClass->channels()->lockForUpdate()->findOrFail($channel->id);
                Gate::forUser($actor)->authorize('viewChannel', [$schoolClass, $channel]);

                return $channel->messages()->create([
                    'sender_id' => $actor->id,
                    'body' => $request->validated('body'),
                    'client_uuid' => $request->validated('client_uuid'),
                ]);
            });
        // A failed realtime signal must not make a committed message appear unsent.
        rescue(fn () => event(new SchoolClassChannelMessageSent($message)), report: false);

        return redirect()->route('classes.channels.show', [$schoolClass, $channel])->with('success', 'Message sent.');
    }
}
