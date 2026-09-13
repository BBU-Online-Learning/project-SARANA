<?php

use App\Http\Controllers\Chat\AttachmentController;
use App\Http\Controllers\Chat\ChatRoomController;
use App\Http\Controllers\Chat\GroupMembershipController;
use App\Http\Controllers\Chat\MessageController;
use App\Http\Controllers\Chat\MessageReactionController;
use App\Http\Controllers\Chat\MessageSearchController;
use App\Http\Controllers\Chat\PresenceController;
use App\Http\Controllers\Chat\ReadReceiptController;
use App\Http\Controllers\Chat\VoiceCallController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'twofactor.setup'])->prefix('chat')->group(function (): void {
    Route::get('/rooms/{room}/access', [GroupMembershipController::class, 'access'])->name('chat.rooms.access');
    Route::get('/groups/{room}', [GroupMembershipController::class, 'show'])->name('chat.groups.show');
    Route::patch('/groups/{room}', [GroupMembershipController::class, 'update'])->name('chat.groups.update');
    Route::post('/groups/{room}/members', [GroupMembershipController::class, 'add'])->name('chat.groups.members.store');
    Route::delete('/groups/{room}/members/{user}', [GroupMembershipController::class, 'remove'])->withTrashed()->name('chat.groups.members.destroy');
    Route::post('/groups/{room}/leave', [GroupMembershipController::class, 'leave'])->name('chat.groups.leave');
    Route::post('/groups/{room}/typing', [GroupMembershipController::class, 'typing'])->middleware('throttle:messages')->name('chat.groups.typing');
});

Route::middleware(['auth', 'twofactor.setup'])->prefix('chat/attachments')->name('chat.attachments.')->group(function (): void {
    Route::get('/{attachment}', [AttachmentController::class, 'show'])->name('show');
    Route::get('/{attachment}/thumbnail', [AttachmentController::class, 'thumbnail'])->name('thumbnail');
    Route::get('/{attachment}/download', [AttachmentController::class, 'download'])->name('download');
});

Route::middleware(['auth', 'twofactor.setup'])->prefix('chat')->name('chat.')->group(function (): void {
    Route::get('/calls/current', [VoiceCallController::class, 'current'])->name('calls.current');
    Route::get('/calls/ice', [VoiceCallController::class, 'ice'])->middleware('throttle:60,1')->name('calls.ice');
    Route::post('/calls/{call}/heartbeat', [VoiceCallController::class, 'heartbeat'])->middleware('throttle:30,1')->name('calls.heartbeat');
    Route::post('/rooms/{room}/calls', [VoiceCallController::class, 'store'])
        ->middleware('throttle:call-start')->name('calls.store');
    Route::post('/calls/{call}/accept', [VoiceCallController::class, 'accept'])->name('calls.accept');
    Route::post('/calls/{call}/decline', [VoiceCallController::class, 'decline'])->name('calls.decline');
    Route::post('/calls/{call}/cancel', [VoiceCallController::class, 'cancel'])->name('calls.cancel');
    Route::post('/calls/{call}/end', [VoiceCallController::class, 'end'])->name('calls.end');
    Route::post('/calls/{call}/timeout', [VoiceCallController::class, 'timeout'])->name('calls.timeout');
    Route::post('/calls/{call}/fail', [VoiceCallController::class, 'fail'])->name('calls.fail');
    Route::post('/calls/{call}/signal', [VoiceCallController::class, 'signal'])
        ->middleware('throttle:call-signals')->name('calls.signal');
});

Route::prefix('chat')  // Adds /chat to the beginning of every route inside the group. ex:/chat/rooms
    ->name('chat.')     // Adds chat. as a prefix to the route name. ex:chat.index
    ->middleware([
        'auth',     // Means the user must be logged in.
        'twofactor.setup',
        'throttle:messages',    // Applies rate limiting.
    ])
    ->group(function () {   // Everything inside the callback shares the same prefix, name prefix, and middleware.

        /*
        |--------------------------------------------------------------------------
        | CHAT ROOMS
        |--------------------------------------------------------------------------
        */

        Route::get('/', [ChatRoomController::class, 'index'])->name('index');

        Route::post('/direct', [ChatRoomController::class, 'createDirectMessage'])
            ->name('direct.create');

        Route::post('/group', [ChatRoomController::class, 'createGroup'])
            ->name('group.create');

        Route::get('/rooms/{room}', [ChatRoomController::class, 'show'])->name('rooms.show');
        Route::post('/rooms/{room}/read', [ReadReceiptController::class, 'store'])->name('rooms.read');
        Route::post('/rooms/{room}/mark-read', [ReadReceiptController::class, 'store']);
        Route::get('/rooms/{room}/reads', [ReadReceiptController::class, 'index'])->name('rooms.reads');
        Route::get('/messages/{message}/reads', [ReadReceiptController::class, 'show'])->name('messages.reads');
        /*
        |--------------------------------------------------------------------------
        | MESSAGES
        |--------------------------------------------------------------------------
        */
        Route::get('/rooms/{room}/older-messages', [MessageController::class, 'olderMessages']);

        Route::post('/rooms/{room}/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::put('/messages/{message}', [MessageController::class, 'update']);
        Route::delete('/messages/{message}', [MessageController::class, 'destroy']);
        Route::post('/messages/{message}/hide', [MessageController::class, 'hideForMe']);
        Route::get('/messages/{message}/html', [MessageController::class, 'renderHtml'])->middleware('auth');

        /*
        |--------------------------------------------------------------------------
        | MESSAGES REACTION
        |--------------------------------------------------------------------------
        */
        Route::post('/messages/{message}/reactions', [MessageReactionController::class, 'toggle'])
            ->name('messages.reactions.toggle');

        /*
        |--------------------------------------------------------------------------
        | check every 60000 for if user online fo update last_seen_at
        |--------------------------------------------------------------------------
        */
        Route::post('/presence/ping', [PresenceController::class, 'touchLastSeen'])
            ->name('presence.ping');
        /*
        |--------------------------------------------------------------------------
        | Search message
        |--------------------------------------------------------------------------
        */
        Route::get('/rooms/{room}/messages/search', [MessageSearchController::class, 'searchInRoom']);
    });
