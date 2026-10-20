<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceReportService;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AttendanceRecord;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $reports,
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * Recap across the school for a date range.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        $school = $this->currentSchool->school();

        return view('attendance.reports.index', [
            'from' => $from,
            'to' => $to,
            'byGroup' => $this->reports->byStudyGroup($school, $from->toDateString(), $to->toDateString()),
            'byDate' => $this->reports->byDate($school, $from->toDateString(), $to->toDateString()),
        ]);
    }

    /**
     * CSV export. Tenant and permission are applied server-side; nothing from
     * the request is trusted as an authority.
     */
    public function export(Request $request): Response
    {
        $this->authorize('export', AttendanceRecord::class);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'study_group_id' => ['nullable', 'integer'],
        ]);

        $school = $this->currentSchool->school();
        $actor = Auth::user();

        $group = null;

        if (($groupId = $data['study_group_id'] ?? null) !== null) {
            // Scoped by SchoolScope, so a foreign id resolves to null → 404.
            $group = StudyGroup::find($groupId);

            abort_unless($group !== null, 404);
        }

        $csv = $this->reports->toCsv($school, $data['from'], $data['to'], $group);

        // Audit log untuk ekspor data sensitif (PRD-08).
        $this->audit->record(
            action: 'attendance.exported',
            school: $school,
            actor: $actor,
            metadata: [
                'from' => $data['from'],
                'to' => $data['to'],
                'study_group_id' => $groupId ?? null,
                'rows' => substr_count($csv, "\n") - 1, // kurangi header
            ],
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="absensi-%s-to-%s.csv"',
                $data['from'],
                $data['to'],
            ),
        ]);
    }
}
