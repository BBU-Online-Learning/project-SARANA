<?php

namespace App\Http\Controllers;

use App\Models\ClassMeeting;
use App\Models\SchoolClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingAttendanceController extends Controller
{
    public function show(SchoolClass $schoolClass, ClassMeeting $meeting): View
    {
        $this->authorizeReport($schoolClass, $meeting);

        return view('meetings.attendance', [
            'schoolClass' => $schoolClass,
            'meeting' => $meeting,
            'rows' => $this->rows($schoolClass, $meeting),
        ]);
    }

    public function export(SchoolClass $schoolClass, ClassMeeting $meeting): StreamedResponse
    {
        $this->authorizeReport($schoolClass, $meeting);
        $rows = $this->rows($schoolClass, $meeting);

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Email', 'Sessions', 'First joined (UTC)', 'Last left (UTC)', 'Total seconds', 'Total minutes', 'In room']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $this->csvText($row['name']), $this->csvText($row['email']), $row['sessions'],
                    $row['first_joined_at']?->utc()->toDateTimeString(),
                    $row['last_left_at']?->utc()->toDateTimeString(),
                    $row['total_seconds'], round($row['total_seconds'] / 60, 2),
                    $row['in_room'] ? 'Yes' : 'No',
                ]);
            }
            fclose($output);
        }, 'meeting-'.$meeting->id.'-attendance.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeReport(SchoolClass $schoolClass, ClassMeeting $meeting): void
    {
        abort_unless((int) $meeting->school_class_id === (int) $schoolClass->id, 404);
        Gate::authorize('viewAttendance', $meeting);
    }

    /** @return Collection<int, array{name: string, email: string, sessions: int, first_joined_at: mixed, last_left_at: mixed, total_seconds: int, in_room: bool}> */
    private function rows(SchoolClass $schoolClass, ClassMeeting $meeting): Collection
    {
        $sessions = $meeting->attendanceSessions()->whereNotNull('joined_at')->with('user')->get();
        $students = $schoolClass->members()->wherePivot('role', 'student')->get();
        $users = $students->merge($sessions->pluck('user')->filter())->unique('id')->sortBy('name');
        $asOf = now();

        return $users->map(function ($user) use ($sessions, $asOf): array {
            $userSessions = $sessions->where('user_id', $user->id);
            $totalSeconds = $userSessions->sum(function ($session) use ($asOf): int {
                return max(0, (int) $session->joined_at->diffInSeconds($session->left_at ?? $asOf, false));
            });

            return [
                'name' => $user->name,
                'email' => $user->email,
                'sessions' => $userSessions->count(),
                'first_joined_at' => $userSessions->min('joined_at'),
                'last_left_at' => $userSessions->max('left_at'),
                'total_seconds' => $totalSeconds,
                'in_room' => $userSessions->contains(fn ($session): bool => $session->left_at === null),
            ];
        })->values();
    }

    private function csvText(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
