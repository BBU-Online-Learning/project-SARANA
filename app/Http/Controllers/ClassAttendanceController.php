<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\AttendanceReportRequest;
use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Http\Requests\Attendance\CorrectAttendanceRequest;
use App\Http\Requests\Attendance\OpenRegisterRequest;
use App\Http\Requests\Attendance\ReviewAttendanceRequest;
use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceRegister;
use App\Models\SchoolClass;
use App\Services\ClassAccessService;
use App\Services\ClassAttendanceService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassAttendanceController extends Controller
{
    public function mine(Request $request, ClassAccessService $access): View
    {
        abort_unless($access->ready($request->user()), 403);
        $records = ClassAttendanceRecord::query()->where('student_id', $request->user()->id)
            ->whereHas('register', fn ($query) => $query->whereNotNull('finalized_at'))
            ->with('register.schoolClass')->latest('id')->paginate(30);

        return view('attendance.mine', compact('records'));
    }

    public function index(Request $request, SchoolClass $schoolClass): View
    {
        Gate::authorize('viewAny', [ClassAttendanceRegister::class, $schoolClass]);
        $canReport = Gate::allows('report', [ClassAttendanceRegister::class, $schoolClass]);
        $registers = $canReport
            ? $schoolClass->attendanceRegisters()->withCount('records')->orderByDesc('attendance_date')->paginate(20)
            : $schoolClass->attendanceRegisters()->whereNotNull('finalized_at')->whereHas('records', fn ($query) => $query->where('student_id', $request->user()->id))
                ->orderByDesc('attendance_date')->paginate(20);

        return view('attendance.index', compact('schoolClass', 'registers', 'canReport'));
    }

    public function open(OpenRegisterRequest $request, SchoolClass $schoolClass, ClassAttendanceService $attendance): RedirectResponse
    {
        $register = $attendance->open($request->user(), $schoolClass, $request->validated('attendance_date'));

        return redirect()->route('classes.attendance.show', [$schoolClass, $register]);
    }

    public function show(Request $request, SchoolClass $schoolClass, ClassAttendanceRegister $register): View
    {
        $this->assertClass($schoolClass, $register);
        $isTeacher = Gate::allows('view', $register);
        if (! $isTeacher) {
            Gate::authorize('viewAny', [ClassAttendanceRegister::class, $schoolClass]);
            abort_unless($register->finalized_at && $register->records()->where('student_id', $request->user()->id)->exists(), 403);
        }
        $records = $register->records()->with('corrections.corrector')
            ->when(! $isTeacher, fn ($query) => $query->where('student_id', $request->user()->id))->get();

        return view('attendance.show', compact('schoolClass', 'register', 'records', 'isTeacher'));
    }

    public function bulk(BulkAttendanceRequest $request, SchoolClass $schoolClass, ClassAttendanceRegister $register, ClassAttendanceService $attendance): RedirectResponse
    {
        $this->assertClass($schoolClass, $register);
        $attendance->bulk($request->user(), $schoolClass, $register, $request->validated('entries'));

        return back()->with('status', 'Attendance saved. Review the full roster before finalizing.');
    }

    public function review(ReviewAttendanceRequest $request, SchoolClass $schoolClass, ClassAttendanceRegister $register, ClassAttendanceService $attendance): RedirectResponse
    {
        $this->assertClass($schoolClass, $register);
        $attendance->review($request->user(), $schoolClass, $register);

        return back()->with('status', 'Roster reviewed. Finalization is now available.');
    }

    public function finalize(Request $request, SchoolClass $schoolClass, ClassAttendanceRegister $register, ClassAttendanceService $attendance): RedirectResponse
    {
        $this->assertClass($schoolClass, $register);
        $attendance->finalize($request->user(), $schoolClass, $register);

        return back()->with('status', 'Attendance finalized. Further changes require a reason.');
    }

    public function correct(CorrectAttendanceRequest $request, SchoolClass $schoolClass, ClassAttendanceRegister $register, ClassAttendanceRecord $record, ClassAttendanceService $attendance): RedirectResponse
    {
        $this->assertClass($schoolClass, $register);
        abort_unless((int) $record->class_attendance_register_id === (int) $register->id, 404);
        $data = $request->validated();
        $attendance->correct($request->user(), $schoolClass, $register, $record, $data['status'], $data['note'] ?? null, $data['reason']);

        return back()->with('status', 'Correction recorded.');
    }

    public function report(AttendanceReportRequest $request, SchoolClass $schoolClass): View
    {
        Gate::authorize('report', [ClassAttendanceRegister::class, $schoolClass]);
        $registers = $this->reportQuery($schoolClass, $request->validated())
            ->withCount(['records', 'records as present_count' => fn ($query) => $query->where('status', 'present'),
                'records as absent_count' => fn ($query) => $query->where('status', 'absent'),
                'records as late_count' => fn ($query) => $query->where('status', 'late'),
                'records as excused_count' => fn ($query) => $query->where('status', 'excused')])
            ->orderByDesc('attendance_date')->paginate(30)->withQueryString();

        return view('attendance.report', compact('schoolClass', 'registers'));
    }

    public function export(AttendanceReportRequest $request, SchoolClass $schoolClass): StreamedResponse
    {
        Gate::authorize('report', [ClassAttendanceRegister::class, $schoolClass]);
        $filters = $request->validated();

        return response()->streamDownload(function () use ($schoolClass, $filters): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Student', 'Status', 'Note', 'Finalized at', 'Corrections', 'Correction history']);
            $this->reportQuery($schoolClass, $filters)->with(['records.corrections'])
                ->orderBy('attendance_date')->chunk(100, function ($registers) use ($output): void {
                    foreach ($registers as $register) {
                        foreach ($register->records as $record) {
                            fputcsv($output, [
                                $register->attendance_date->toDateString(),
                                $this->csvSafe($record->student_name_snapshot),
                                $record->status ?? 'unmarked',
                                $this->csvSafe($record->note ?? ''),
                                $register->finalized_at?->toDateTimeString() ?? '',
                                $record->corrections->count(),
                                $this->csvSafe($record->corrections->map(fn ($correction): string => $correction->corrected_at->toDateTimeString().': '.$correction->previous_status.' to '.$correction->new_status.' ('.$correction->reason.')')->join(' | ')),
                            ]);
                        }
                    }
                });
            fclose($output);
        }, 'class-'.$schoolClass->id.'-attendance.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function reportQuery(SchoolClass $schoolClass, array $filters): HasMany
    {
        return $schoolClass->attendanceRegisters()
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('attendance_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('attendance_date', '<=', $to));
    }

    private function assertClass(SchoolClass $schoolClass, ClassAttendanceRegister $register): void
    {
        abort_unless((int) $register->school_class_id === (int) $schoolClass->id, 404);
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
