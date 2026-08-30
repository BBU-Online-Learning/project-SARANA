<?php

use App\Http\Controllers\Chat\ChatRoomController;
use App\Http\Controllers\Chat\MessageController;
use App\Http\Controllers\Chat\MessageReactionController;
use App\Http\Controllers\Chat\MessageSearchController;
use App\Http\Controllers\Chat\PresenceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;





Route::prefix('chat')  //Adds /chat to the beginning of every route inside the group. ex:/chat/rooms
    ->name('chat.')     //Adds chat. as a prefix to the route name. ex:chat.index
    ->middleware([
        'auth',     //Means the user must be logged in.
        'twofactor.setup',
        'throttle:messages',    //Applies rate limiting.
    ])
    ->group(function () {   //Everything inside the callback shares the same prefix, name prefix, and middleware.

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
        Route::post('/rooms/{room}/mark-read', [ChatRoomController::class, 'markRead']);
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
