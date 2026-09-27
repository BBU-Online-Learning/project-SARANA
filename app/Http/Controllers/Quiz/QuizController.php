<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\StoreQuizRequest;
use App\Models\Quiz;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function create(SchoolClass $schoolClass): View
    {
        Gate::authorize('create', [Quiz::class, $schoolClass]);

        return view('quizzes.create', compact('schoolClass'));
    }

    public function store(StoreQuizRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        Gate::authorize('create', [Quiz::class, $schoolClass]);
        $quiz = $schoolClass->quizzes()->create($request->validated() + ['creator_id' => $request->user()->id, 'status' => 'draft']);

        return redirect()->route('classes.quizzes.edit', [$schoolClass, $quiz])->with('success', 'Quiz draft created. Add your questions.');
    }

    public function edit(SchoolClass $schoolClass, Quiz $quiz): View
    {
        $this->ensureClass($schoolClass, $quiz);
        Gate::authorize('update', $quiz);
        $quiz->load('questions.options');

        return view('quizzes.edit', compact('schoolClass', 'quiz'));
    }

    public function update(StoreQuizRequest $request, SchoolClass $schoolClass, Quiz $quiz): RedirectResponse
    {
        $this->ensureClass($schoolClass, $quiz);
        Gate::authorize('update', $quiz);
        $quiz->update($request->validated());

        return back()->with('success', 'Quiz details saved.');
    }

    public function preview(SchoolClass $schoolClass, Quiz $quiz): View
    {
        $this->ensureClass($schoolClass, $quiz);
        Gate::authorize('manage', $quiz);
        $quiz->load('questions.options');

        return view('quizzes.preview', compact('schoolClass', 'quiz'));
    }

    public function publish(SchoolClass $schoolClass, Quiz $quiz): RedirectResponse
    {
        $this->ensureClass($schoolClass, $quiz);
        Gate::authorize('publish', $quiz);
        abort_if($quiz->questions()->count() === 0, 422, 'Add at least one question before publishing.');
        $quiz->update(['status' => 'published', 'published_at' => now()]);

        return redirect()->route('classes.quizzes.assign.create', [$schoolClass, $quiz])->with('success', 'Quiz published. Choose who receives it.');
    }

    public function destroy(SchoolClass $schoolClass, Quiz $quiz): RedirectResponse
    {
        $this->ensureClass($schoolClass, $quiz);
        Gate::authorize('delete', $quiz);
        $quiz->delete();

        return redirect()->route('classes.assessments.index', $schoolClass)->with('success', 'Quiz deleted.');
    }

    private function ensureClass(SchoolClass $schoolClass, Quiz $quiz): void
    {
        abort_unless($quiz->school_class_id === $schoolClass->id, 404);
    }
}
