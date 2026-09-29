<?php

namespace App\Http\Controllers;

use App\Http\Requests\Academics\SaveReportingPeriodRequest;
use App\Http\Requests\Academics\TransitionReportingPeriodRequest;
use App\Models\AcademicYear;
use App\Models\ReportingPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportingPeriodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ReportingPeriods/Index', [
            'title' => 'Reporting periods',
            'success' => session('success'),
            'years' => AcademicYear::query()->whereNotNull('starts_on')->whereNotNull('ends_on')
                ->orderByDesc('starts_on')->get(['id', 'name', 'starts_on', 'ends_on'])
                ->map(fn (AcademicYear $year): array => [
                    'id' => $year->id,
                    'name' => $year->name,
                    'startsOn' => $year->starts_on->toDateString(),
                    'endsOn' => $year->ends_on->toDateString(),
                ]),
            'periods' => ReportingPeriod::query()->with('academicYear:id,name')
                ->orderBy('academic_year_id')->orderBy('sequence')->orderBy('id')->get()
                ->map(fn (ReportingPeriod $period): array => [
                    'id' => $period->id,
                    'academicYearId' => $period->academic_year_id,
                    'academicYearName' => $period->academicYear->name,
                    'parentId' => $period->parent_id,
                    'name' => $period->name,
                    'code' => $period->code,
                    'sequence' => $period->sequence,
                    'startsOn' => $period->starts_on->toDateString(),
                    'endsOn' => $period->ends_on->toDateString(),
                    'status' => $period->status,
                ]),
            'urls' => [
                'index' => route('academics.reporting-periods.index'),
                'store' => route('academics.reporting-periods.store'),
                'academics' => route('academics.index'),
            ],
        ]);
    }

    public function store(SaveReportingPeriodRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->validateContext($data);
        ReportingPeriod::query()->create($data + ['status' => 'draft']);

        return redirect()->route('academics.reporting-periods.index')->with('success', 'Reporting period created.');
    }

    public function update(SaveReportingPeriodRequest $request, ReportingPeriod $reportingPeriod): RedirectResponse
    {
        if ($reportingPeriod->status !== 'draft') {
            throw ValidationException::withMessages(['reporting_period' => 'Only draft reporting periods can be edited.']);
        }

        $data = $request->validated();
        if ((int) $data['academic_year_id'] !== (int) $reportingPeriod->academic_year_id) {
            throw ValidationException::withMessages(['academic_year_id' => 'A reporting period cannot move to another academic year.']);
        }

        $this->validateContext($data, $reportingPeriod);
        $reportingPeriod->update($data);

        return redirect()->route('academics.reporting-periods.index')->with('success', 'Reporting period updated.');
    }

    public function transition(TransitionReportingPeriodRequest $request, ReportingPeriod $reportingPeriod): RedirectResponse
    {
        $targetStatus = $request->validated('status');
        $allowed = ($reportingPeriod->status === 'draft' && $targetStatus === 'open')
            || ($reportingPeriod->status === 'open' && $targetStatus === 'closed');

        if (! $allowed) {
            throw ValidationException::withMessages(['status' => 'Reporting periods must move from draft to open to closed.']);
        }

        $reportingPeriod->update(['status' => $targetStatus]);

        return redirect()->route('academics.reporting-periods.index')->with('success', 'Reporting period '.$targetStatus.'.');
    }

    /** @param array<string, mixed> $data */
    private function validateContext(array $data, ?ReportingPeriod $period = null): void
    {
        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
        if ($year->starts_on === null || $year->ends_on === null
            || $data['starts_on'] < $year->starts_on->toDateString()
            || $data['ends_on'] > $year->ends_on->toDateString()) {
            throw ValidationException::withMessages(['starts_on' => 'The reporting period must fall within a dated academic year.']);
        }

        if (! empty($data['parent_id'])) {
            $parent = ReportingPeriod::query()->findOrFail($data['parent_id']);
            if ((int) $parent->academic_year_id !== (int) $year->id) {
                throw ValidationException::withMessages(['parent_id' => 'The parent must belong to the selected academic year.']);
            }
            for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->parent) {
                if ($period !== null && $ancestor->is($period)) {
                    throw ValidationException::withMessages(['parent_id' => 'A reporting period cannot be its own parent or descendant.']);
                }
            }
            if ($data['starts_on'] < $parent->starts_on->toDateString()
                || $data['ends_on'] > $parent->ends_on->toDateString()) {
                throw ValidationException::withMessages(['starts_on' => 'A child period must fall within its parent dates.']);
            }
        }

        if ($period !== null && $period->children()->where(function ($query) use ($data): void {
            $query->whereDate('starts_on', '<', $data['starts_on'])
                ->orWhereDate('ends_on', '>', $data['ends_on']);
        })->exists()) {
            throw ValidationException::withMessages(['starts_on' => 'The reporting period must contain all existing child periods.']);
        }
    }
}
