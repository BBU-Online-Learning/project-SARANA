<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Services\ClassAccessService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function overview(ClassAccessService $access): View
    {
        $user = request()->user();
        abort_unless($access->ready($user), 403);
        $teacher = $user->role->name === 'teacher';
        if ($teacher) {
            $classes = $user->schoolClasses()
                ->wherePivotIn('role', ['owner', 'teacher'])
                ->orderBy('school_classes.name')
                ->get();
            $classIds = $classes->pluck('id');
            $quizzes = Quiz::query()->whereIn('school_class_id', $classIds)
                ->with(['schoolClass', 'assignments' => fn ($query) => $query->latest()->limit(1)])
                ->withCount(['questions', 'assignments'])->latest()->get();
            $assignments = collect();
        } else {
            $classes = collect();
            $quizzes = collect();
            $assignments = $user->quizAssignments()->with(['schoolClass', 'quiz' => fn ($query) => $query->withCount('questions'), 'attempts' => fn ($query) => $query->where('user_id', $user->id)])->orderBy('due_at')->get();
        }

        return view('quizzes.overview', compact('teacher', 'classes', 'quizzes', 'assignments'));
    }

    public function __invoke(SchoolClass $schoolClass, ClassAccessService $access): View
    {
        Gate::authorize('view', $schoolClass);
        $teacher = $access->teachingRole(request()->user(), $schoolClass) !== null;
        $quizzes = collect();
        $assignments = collect();

        if ($teacher) {
            $quizzes = $schoolClass->quizzes()->withCount('questions')->with('assignments')->latest()->get();
            $assignments = $schoolClass->quizAssignments()->with('quiz')->withCount(['students', 'attempts'])->latest()->get();
        } else {
            $assignments = request()->user()->quizAssignments()->where('school_class_id', $schoolClass->id)
                ->with(['quiz' => fn ($query) => $query->withCount('questions'), 'attempts' => fn ($query) => $query->where('user_id', request()->user()->id)])
                ->orderBy('starts_at')->get();
        }

        return view('quizzes.index', compact('schoolClass', 'teacher', 'quizzes', 'assignments'));
    }
}
