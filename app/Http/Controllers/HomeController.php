<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function index(Request $request, ClassAccessService $access): Response
    {
        $user = $request->user();
        abort_unless($access->ready($user), 403);
        $administrator = $access->administrator($user);
        $classes = $administrator ? SchoolClass::query() : $user->schoolClasses()->wherePivotIn('role', ['owner', 'teacher', 'student']);
        $classCounts = [
            'active' => (clone $classes)->whereNull('archived_at')->count(),
            'archived' => (clone $classes)->whereNotNull('archived_at')->count(),
        ];
        $recentClasses = (clone $classes)->orderByDesc('school_classes.updated_at')->orderByDesc('school_classes.id')->limit(5)->get();
        $conversationCounts = [
            'direct' => $user->chatRooms()->where('type', 'direct')->count(),
            'group' => $user->chatRooms()->where('type', 'group')->count(),
        ];
        $accountCounts = [];
        foreach (Role::manageableNames($user) as $role) {
            $accountCounts[$role] = User::query()->where('id', '!=', $user->id)
                ->whereHas('role', fn ($query) => $query->where('name', $role))->count();
        }

        return response()->view('home', compact('user', 'administrator', 'classCounts', 'recentClasses', 'conversationCounts', 'accountCounts'))
            ->header('Cache-Control', 'private, no-store');
    }
}
