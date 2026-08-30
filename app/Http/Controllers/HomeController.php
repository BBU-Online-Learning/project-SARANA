<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();

        // If a teacher or student somehow opens /home, send them to chat instead.
        if ($user && ! $user->can('access-admin')) {
            return redirect()->route('chat.index');
        }

        return view('home');
    }
}
