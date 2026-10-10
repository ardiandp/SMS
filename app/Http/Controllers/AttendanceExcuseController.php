<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\ExcuseService;
use App\Enums\ExcuseStatus;
use App\Http\Requests\StoreAttendanceExcuseRequest;
use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceExcuseController extends Controller
{
    public function __construct(private readonly ExcuseService $excuses) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AttendanceExcuse::class);

        $excuses = AttendanceExcuse::query()
            ->with(['record.session.studyGroup', 'student', 'reviewer'])
            ->when($request->filled('status'), fn ($query) => $query
                ->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('attendance.excuses.index', ['excuses' => $excuses]);
    }

    public function store(StoreAttendanceExcuseRequest $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $this->excuses->submit(
            $attendanceRecord,
            $request->string('reason')->toString(),
            $request->user(),
            $request->file('document'),
        );

        return redirect()
            ->route('attendance.sessions.show', $attendanceRecord->attendance_session_id)
            ->with('status', 'Pengajuan alasan terkirim.');
    }

    public function review(Request $request, AttendanceExcuse $attendanceExcuse): RedirectResponse
    {
        $this->authorize('review', $attendanceExcuse);

        $data = $request->validate([
            'status' => ['required', Rule::in([ExcuseStatus::Approved->value, ExcuseStatus::Rejected->value])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->excuses->review(
            $attendanceExcuse,
            ExcuseStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null,
        );

        return back()->with('status', 'Alasan absensi diproses.');
    }

    /**
     * Evidence file, served through policy from the private disk.
     */
    public function download(AttendanceExcuse $attendanceExcuse): BinaryFileResponse
    {
        $this->authorize('download', $attendanceExcuse);

        $path = $this->excuses->documentPath($attendanceExcuse);

        abort_unless($path !== null, 404);

        return response()->download($path, basename($attendanceExcuse->document_path), [
            'Content-Type' => $attendanceExcuse->mime_type ?: 'application/octet-stream',
        ]);
    }
}
