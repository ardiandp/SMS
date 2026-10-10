<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceRecordService;
use App\Domain\Attendance\AttendanceReportService;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AttendanceStatus;
use App\Http\Requests\CorrectAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\StudyGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceRecordController extends Controller
{
    public function __construct(private readonly AttendanceRecordService $records) {}

    /**
     * Correct one record on a finalized session.
     */
    public function correct(
        CorrectAttendanceRequest $request,
        AttendanceRecord $attendanceRecord,
    ): RedirectResponse {
        $this->records->correct(
            $attendanceRecord,
            AttendanceStatus::from($request->string('status')->toString()),
            $request->user(),
            $request->string('reason')->toString(),
        );

        return redirect()
            ->route('attendance.sessions.show', $attendanceRecord->attendance_session_id)
            ->with('status', 'Koreksi absensi tercatat.');
    }

    /**
     * Add a student who joined the group after the session was opened.
     */
    public function attach(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $this->authorize('update', $attendanceSession);

        $data = $request->validate([
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where(
                    'school_id',
                    app(CurrentSchool::class)->school()?->getKey(),
                ),
            ],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
        ]);

        $this->records->attachStudent(
            $attendanceSession,
            Student::findOrFail($data['student_id']),
            AttendanceStatus::from($data['status']),
            $request->user(),
        );

        return redirect()->route('attendance.sessions.show', $attendanceSession)
            ->with('status', 'Siswa ditambahkan ke sesi absensi.');
    }

    /**
     * Per-student recap across a date range.
     */
    public function studentSummary(Request $request, Student $student): View
    {
        $this->authorize('view', $student);

        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        $records = AttendanceRecord::query()
            ->where('student_id', $student->getKey())
            ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [$from, $to]))
            ->with('session.studyGroup')
            ->orderByDesc('session.session_date')
            ->get();

        return view('attendance.records.student-summary', [
            'student' => $student,
            'records' => $records,
            'from' => $from,
            'to' => $to,
            'summary' => app(AttendanceReportService::class)
                ->forStudent($student, $from->toDateString(), $to->toDateString()),
        ]);
    }

    /**
     * Per-study-group recap.
     */
    public function studyGroupSummary(Request $request, StudyGroup $studyGroup): View
    {
        $this->authorize('view', $studyGroup);

        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        return view('attendance.records.study-group-summary', [
            'studyGroup' => $studyGroup,
            'from' => $from,
            'to' => $to,
            'summary' => app(AttendanceReportService::class)
                ->forStudyGroup($studyGroup, $from->toDateString(), $to->toDateString()),
            'daily' => app(AttendanceReportService::class)
                ->byDate($studyGroup->school, $from->toDateString(), $to->toDateString()),
        ]);
    }
}
