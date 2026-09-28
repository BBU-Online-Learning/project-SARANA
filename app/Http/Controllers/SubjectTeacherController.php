<?php

namespace App\Http\Controllers;

use App\Http\Requests\Academics\AssignSubjectTeacherRequest;
use App\Models\SchoolClass;
use App\Models\TeacherSubjectAssignment;
use App\Services\SubjectTeacherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SubjectTeacherController extends Controller
{
    public function __construct(private SubjectTeacherService $subjectTeachers) {}

    public function store(AssignSubjectTeacherRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->subjectTeachers->assign($request->user(), $schoolClass, (int) $request->validated('subject_id'), (int) $request->validated('user_id'));

        return redirect()->route('classes.show', $schoolClass)->with('success', 'Subject teacher assigned.');
    }

    public function end(SchoolClass $schoolClass, TeacherSubjectAssignment $subjectTeacherAssignment): RedirectResponse
    {
        $this->subjectTeachers->end(Auth::user(), $schoolClass, $subjectTeacherAssignment);

        return redirect()->route('classes.show', $schoolClass)->with('success', 'Subject teacher assignment ended.');
    }
}
