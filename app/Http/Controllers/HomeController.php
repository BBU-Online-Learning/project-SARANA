<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAssignment;
use App\Models\QuizAttempt;
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
        $recentClasses = (clone $classes)
            ->with('creator')
            ->withCount(['members', 'channels'])
            ->orderByDesc('school_classes.updated_at')
            ->orderByDesc('school_classes.id')
            ->limit(5)
            ->get();
        $conversationCounts = [
            'direct' => $user->chatRooms()->where('type', 'direct')->count(),
            'group' => $user->chatRooms()->where('type', 'group')->count(),
        ];
        $accountCounts = [];
        foreach (Role::manageableNames($user) as $role) {
            $accountCounts[$role] = User::query()->where('id', '!=', $user->id)
                ->whereHas('role', fn ($query) => $query->where('name', $role))->count();
        }

        $assessmentSummary = ['quizzes' => 0, 'active_assignments' => 0, 'pending_reviews' => 0];
        $upcomingAssessments = collect();
        $studentAssessmentSummary = ['assigned' => 0, 'available' => 0, 'in_progress' => 0, 'graded' => 0];
        $studentUpcomingAssessments = collect();

        if ($user->role->name === 'teacher') {
            $teachingClassIds = $user->schoolClasses()
                ->wherePivotIn('role', ['owner', 'teacher'])
                ->pluck('school_classes.id');

            $assessmentSummary = [
                'quizzes' => Quiz::query()->whereIn('school_class_id', $teachingClassIds)->count(),
                'active_assignments' => QuizAssignment::query()
                    ->whereIn('school_class_id', $teachingClassIds)
                    ->whereNotIn('status', ['cancelled', 'closed'])
                    ->where('due_at', '>=', now())
                    ->count(),
                'pending_reviews' => QuizAttempt::query()
                    ->where('status', 'pending_review')
                    ->whereHas('assignment', fn ($query) => $query->whereIn('school_class_id', $teachingClassIds))
                    ->count(),
            ];

            $upcomingAssessments = QuizAssignment::query()
                ->whereIn('school_class_id', $teachingClassIds)
                ->whereNotIn('status', ['cancelled', 'closed'])
                ->where('due_at', '>=', now())
                ->with(['quiz:id,title', 'schoolClass:id,name'])
                ->orderBy('due_at')
                ->limit(4)
                ->get();
        } elseif ($user->role->name === 'student') {
            $assignedQuizzes = $user->quizAssignments();
            $studentAssessmentSummary = [
                'assigned' => (clone $assignedQuizzes)->count(),
                'available' => (clone $assignedQuizzes)
                    ->whereNotIn('quiz_assignments.status', ['cancelled', 'closed'])
                    ->where('starts_at', '<=', now())
                    ->where('due_at', '>=', now())
                    ->count(),
                'in_progress' => $user->quizAttempts()->where('status', 'in_progress')->count(),
                'graded' => $user->quizAttempts()->where('status', 'graded')->count(),
            ];
            $studentUpcomingAssessments = (clone $assignedQuizzes)
                ->whereNotIn('quiz_assignments.status', ['cancelled', 'closed'])
                ->where('due_at', '>=', now())
                ->with(['quiz:id,title', 'schoolClass:id,name'])
                ->orderBy('due_at')
                ->limit(4)
                ->get();
        }

        return response()->view('home', compact('user', 'administrator', 'classCounts', 'recentClasses', 'conversationCounts', 'accountCounts', 'assessmentSummary', 'upcomingAssessments', 'studentAssessmentSummary', 'studentUpcomingAssessments'))
            ->header('Cache-Control', 'private, no-store');
    }
}
