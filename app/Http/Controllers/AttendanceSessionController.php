<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceRecordService;
use App\Domain\Attendance\AttendanceSessionService;
use App\Domain\Attendance\StudyGroupRoster;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AttendanceStatus;
use App\Enums\StudyGroupStatus;
use App\Http\Requests\MarkAttendanceRequest;
use App\Http\Requests\OpenAttendanceSessionRequest;
use App\Models\AttendanceSession;
use App\Models\StudyGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceSessionController extends Controller
{
    public function __construct(
        private readonly AttendanceSessionService $sessions,
        private readonly AttendanceRecordService $records,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $sessions = AttendanceSession::query()
            ->with('studyGroup')
            ->withCount('records')
            ->when($request->filled('study_group_id'), fn ($query) => $query
                ->where('study_group_id', $request->integer('study_group_id')))
            ->when($request->filled('date'), fn ($query) => $query
                ->whereDate('session_date', $request->date('date')))
            ->orderByDesc('session_date')
            ->paginate(20)
            ->withQueryString();

        return view('attendance.sessions.index', [
            'sessions' => $sessions,
            'studyGroups' => StudyGroup::where('status', StudyGroupStatus::Active->value)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(OpenAttendanceSessionRequest $request): RedirectResponse
    {
        $group = StudyGroup::findOrFail($request->integer('study_group_id'));

        $session = $this->sessions->open(
            $group,
            $request->date('session_date')->toDateString(),
            $request->user(),
        );

        return redirect()->route('attendance.sessions.show', $session)
            ->with('status', 'Sesi absensi dibuka.');
    }

    /**
     * The register-taking screen.
     */
    public function show(Request $request, AttendanceSession $attendanceSession): View
    {
        $this->authorize('view', $attendanceSession);

        $attendanceSession->load(['studyGroup', 'records.student', 'records.excuse']);

        return view('attendance.sessions.show', [
            'session' => $attendanceSession,
            'records' => $attendanceSession->records
                ->sortBy(fn ($record) => $record->student?->full_name ?? '')
                ->values(),
        ]);
    }

    public function mark(MarkAttendanceRequest $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $changed = $this->records->markMany(
            $attendanceSession,
            $request->statuses(),
            $request->user(),
            $request->input('notes'),
        );

        return redirect()->route('attendance.sessions.show', $attendanceSession)
            ->with('status', "{$changed} catatan absensi diperbarui.");
    }

    public function markAll(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $this->authorize('update', $attendanceSession);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $changed = $this->records->markAll(
            $attendanceSession,
            AttendanceStatus::from($data['status']),
            $request->user(),
            $data['notes'] ?? null,
        );

        return redirect()->route('attendance.sessions.show', $attendanceSession)
            ->with('status', "{$changed} catatan absensi diperbarui.");
    }

    public function finalize(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $this->authorize('finalize', $attendanceSession);

        $this->sessions->finalize($attendanceSession, $request->user());

        return redirect()->route('attendance.sessions.show', $attendanceSession)
            ->with('status', 'Sesi absensi ditutup. Perubahan berikutnya dicatat sebagai koreksi.');
    }

    public function reopen(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $this->authorize('reopen', $attendanceSession);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->sessions->reopen($attendanceSession, $request->user(), $data['reason']);

        return redirect()->route('attendance.sessions.show', $attendanceSession)
            ->with('status', 'Sesi absensi dibuka kembali.');
    }

    /**
     * Roster currently attached to a group, used by the late-add control on the
     * register screen.
     */
    public function roster(Request $request, StudyGroupRoster $roster): View
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $group = StudyGroup::findOrFail($request->integer('study_group_id'));

        abort_unless(
            (int) $group->school_id === (int) app(CurrentSchool::class)->school()?->getKey(),
            404,
        );

        return view('attendance.sessions.roster', [
            'studyGroup' => $group,
            'students' => $roster->studentsOn($group, now()->toDateString()),
        ]);
    }
}
