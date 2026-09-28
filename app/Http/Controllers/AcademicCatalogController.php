<?php

namespace App\Http\Controllers;

use App\Http\Requests\Academics\AssignClassAcademicsRequest;
use App\Http\Requests\Academics\IndexAcademicClassesRequest;
use App\Http\Requests\Academics\StoreAcademicYearRequest;
use App\Http\Requests\Academics\StoreGradeLevelRequest;
use App\Http\Requests\Academics\StoreSubjectRequest;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicMembershipService;
use App\Services\ClassManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AcademicCatalogController extends Controller
{
    public function __construct(private AcademicMembershipService $academicMemberships, private ClassManagementService $classes) {}

    public function index(IndexAcademicClassesRequest $request): View
    {
        $search = $request->validated('search') ?? '';
        $academicYearId = $request->validated('academic_year_id');
        $status = $request->validated('status') ?? 'all';

        return view('academics.index', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => GradeLevel::query()->orderBy('sequence')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'classes' => SchoolClass::query()->with(['academicYear', 'gradeLevel', 'subjects'])
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', '%'.$search.'%')
                            ->orWhere('join_code', 'like', '%'.$search.'%');
                    });
                })
                ->when($academicYearId, fn (Builder $query): Builder => $query->where('academic_year_id', $academicYearId))
                ->when($status === 'active', fn (Builder $query): Builder => $query->whereNull('archived_at'))
                ->when($status === 'archived', fn (Builder $query): Builder => $query->whereNotNull('archived_at'))
                ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'search' => $search,
            'academicYearId' => $academicYearId,
            'status' => $status,
        ]);
    }

    public function edit(SchoolClass $schoolClass): View
    {
        return view('academics.edit', [
            'schoolClass' => $schoolClass->load(['academicYear', 'gradeLevel', 'subjects']),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => GradeLevel::query()->orderBy('sequence')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function storeYear(StoreAcademicYearRequest $request): RedirectResponse
    {
        AcademicYear::query()->create($request->validated());

        return back()->with('success', 'Academic year created.');
    }

    public function storeGrade(StoreGradeLevelRequest $request): RedirectResponse
    {
        GradeLevel::query()->create($request->validated());

        return back()->with('success', 'Grade level created.');
    }

    public function storeSubject(StoreSubjectRequest $request): RedirectResponse
    {
        Subject::query()->create($request->validated());

        return back()->with('success', 'Subject created.');
    }

    public function assignClass(AssignClassAcademicsRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validated();
        $this->classes->withClass($request->user(), $schoolClass, function (User $actor, SchoolClass $schoolClass) use ($validated): void {
            Gate::forUser($actor)->authorize('access-admin');
            $this->academicMemberships->moveToYear($schoolClass, (int) $validated['academic_year_id']);
            $schoolClass->update([
                'academic_year_id' => $validated['academic_year_id'],
                'grade_level_id' => $validated['grade_level_id'] ?? null,
            ]);
            $schoolClass->subjects()->sync($validated['subject_ids'] ?? []);
        });

        return redirect()->route('academics.classes.edit', $schoolClass)->with('success', 'Class academic context updated.');
    }
}
