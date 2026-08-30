<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PresenceController extends Controller
{
    public function touchLastSeen()
    {
        Auth::user()->forceFill([
            'last_seen_at' => now(),
        ])->saveQuietly();

        return response()->noContent(); // matches your Step 1 style
    }
}