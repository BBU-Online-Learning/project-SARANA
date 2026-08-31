<?php

namespace App\Http\Controllers;

use App\Http\Requests\Classes\ClassMessagesRequest;
use App\Http\Requests\Classes\StoreSchoolClassChannelMessageRequest;
use App\Http\Requests\Classes\UpdateSchoolClassChannelMessageRequest;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Services\ClassAccessService;
use App\Services\ClassMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SchoolClassChannelMessageController extends Controller
{
    public function __construct(private ClassMessageService $messages) {}

    public function show(ClassMessagesRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel): Response
    {
        $schoolClass->load(['creator', 'channels']);
        $page = $channel->messages()->with('sender')
            ->when($request->validated('before_id'), fn ($query, $id) => $query->where('id', '<', $id))
            ->orderByDesc('id')->limit(51)->get();
        $hasOlder = $page->count() > 50;
        $messages = $page->take(50)->reverse()->values();
        $membership = app(ClassAccessService::class)->membership($request->user(), $schoolClass);
        $historyPage = $request->has('before_id');
        $canSend = Gate::allows('sendMessage', [$schoolClass, $channel]);
        $initialMessages = $messages->map(fn ($message) => $this->messages->payload($message, $request->user()->id, $canSend));

        return response()->view('classes.channels.show', compact('schoolClass', 'channel', 'messages', 'membership', 'hasOlder', 'historyPage', 'initialMessages'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function index(ClassMessagesRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel): JsonResponse
    {
        $after = (int) $request->validated('after_id', 0);
        $ascending = $request->has('after_id');
        $query = $channel->messages()->with('sender');
        if ($ascending) {
            $query->where('id', '>', $after)->orderBy('id');
        } else {
            $query->when($request->validated('before_id'), fn ($query, $id) => $query->where('id', '<', $id))->orderByDesc('id');
        }
        $page = $request->boolean('sync_only') ? collect() : $query->limit(51)->get();
        $messages = $page->take(50);
        if (! $ascending) {
            $messages = $messages->reverse()->values();
        }
        $canSend = Gate::allows('sendMessage', [$schoolClass, $channel]);
        $serialize = fn ($message): array => $this->messages->payload($message, $request->user()->id, $canSend);
        $updates = $channel->messages()->withTrashed()->with('sender')
            ->whereIn('id', $request->validated('visible_ids', []))->orderBy('id')->get();

        return response()->json([
            'messages' => $messages->map($serialize)->values(),
            'updates' => $updates->map($serialize)->values(),
            'next_id' => $messages->last()?->id ?? $after,
            'before_id' => $messages->first()?->id,
            'has_more' => $page->count() > 50,
            'can_send' => $canSend,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreSchoolClassChannelMessageRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel): JsonResponse|RedirectResponse
    {
        $message = $this->messages->send($request->user(), $schoolClass, $channel, $request->validated());

        return $this->result($request, $schoolClass, $channel, $message, 'Message sent.', $message->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateSchoolClassChannelMessageRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel, SchoolClassChannelMessage $message): JsonResponse|RedirectResponse
    {
        $message = $this->messages->mutate($request->user(), $schoolClass, $channel, $message->id, $request->validated('body'));

        return $this->result($request, $schoolClass, $channel, $message, 'Message updated.');
    }

    public function destroy(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, SchoolClassChannelMessage $message): JsonResponse|RedirectResponse
    {
        $message = $this->messages->mutate($request->user(), $schoolClass, $channel, $message->id, null);

        return $this->result($request, $schoolClass, $channel, $message, 'Message deleted.');
    }

    private function result(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, SchoolClassChannelMessage $message, string $notice, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->messages->payload($message->load('sender'), $request->user()->id,
                Gate::allows('sendMessage', [$schoolClass, $channel]))], $status)->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('classes.channels.show', [$schoolClass, $channel])->with('success', $notice);
    }
}
