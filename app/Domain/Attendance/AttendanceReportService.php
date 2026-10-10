<?php

namespace App\Domain\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\School;
use App\Models\Student;
use App\Models\StudyGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance totals and exports.
 *
 * Definitions used by every number in this class — stated explicitly because
 * PRD-03 requires the recap to be documented rather than implied:
 *
 *  - A session's `total` is the number of attendance records in it, which equals
 *    the roster size on that date.
 *  - Counts per status are mutually exclusive and always sum to `total`.
 *  - `attendance_rate` = present / total * 100.
 *  - `effective_attendance_rate` = (present + sick + permitted) / total * 100,
 *    i.e. the school-day attendance rate that counts excused absences.
 *  - Sick and permitted are NOT counted as present: the two rates are always
 *    reported separately so the school can apply its own policy.
 *  - A period with no records yields rates of null, never 0, so "no data" is not
 *    confused with "nobody attended".
 *
 * Every query here is tenant-scoped by the caller-supplied school id and never
 * trusts a school_id from the request.
 */
class AttendanceReportService
{
    /**
     * Status totals for one session.
     *
     * @return array{total: int, counts: array<string, int>, attendance_rate: ?float, effective_attendance_rate: ?float}
     */
    public function forSession(AttendanceSession $session): array
    {
        $counts = $this->countsByStatus(
            AttendanceRecord::query()
                ->where('attendance_session_id', $session->getKey())
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->get()
        );

        return $this->summarise($counts);
    }

    /**
     * Status totals for one student over a date range.
     *
     * @return array{total: int, counts: array<string, int>, attendance_rate: ?float, effective_attendance_rate: ?float}
     */
    public function forStudent(Student $student, string $from, string $to): array
    {
        $counts = $this->countsByStatus(
            AttendanceRecord::query()
                ->where('student_id', $student->getKey())
                ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [$from, $to]))
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->get()
        );

        return $this->summarise($counts);
    }

    /**
     * Totals for one study group over a date range.
     *
     * @return array{total: int, counts: array<string, int>, attendance_rate: ?float, effective_attendance_rate: ?float}
     */
    public function forStudyGroup(StudyGroup $group, string $from, string $to): array
    {
        $counts = $this->countsByStatus(
            AttendanceRecord::query()
                ->whereHas('session', fn ($query) => $query
                    ->where('study_group_id', $group->getKey())
                    ->whereBetween('session_date', [$from, $to]))
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->get()
        );

        return $this->summarise($counts);
    }

    /**
     * One row per study group for a date range — the main recap table.
     *
     * @return array<string, array<string, mixed>>
     */
    public function byStudyGroup(School $school, string $from, string $to): array
    {
        $rows = DB::table('attendance_records as ar')
            ->join('attendance_sessions as asess', 'asess.id', '=', 'ar.attendance_session_id')
            ->where('ar.school_id', $school->getKey())
            ->whereBetween('asess.session_date', [$from, $to])
            ->join('study_groups as sg', 'sg.id', '=', 'asess.study_group_id')
            ->selectRaw('
                sg.id as study_group_id,
                sg.name as study_group_name,
                ar.status,
                COUNT(*) as aggregate
            ')
            ->groupBy('sg.id', 'sg.name', 'ar.status')
            ->get();

        return $this->pivot($rows);
    }

    /**
     * Daily recap rows for a date range, grouped by date.
     *
     * @return array<string, array<string, int>>
     */
    public function byDate(School $school, string $from, string $to): array
    {
        $rows = DB::table('attendance_records as ar')
            ->join('attendance_sessions as asess', 'asess.id', '=', 'ar.attendance_session_id')
            ->where('ar.school_id', $school->getKey())
            ->whereBetween('asess.session_date', [$from, $to])
            ->selectRaw('asess.session_date, ar.status, COUNT(*) as aggregate')
            ->groupBy('asess.session_date', 'ar.status')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $date = (string) $row->session_date;

            $result[$date] ??= ['total' => 0];

            $result[$date][$row->status] = (int) $row->aggregate;
            $result[$date]['total'] += (int) $row->aggregate;
        }

        return $result;
    }

    /**
     * Flat rows for CSV export. Tenant scoped by the caller-supplied school.
     *
     * @return Collection<int, object>
     */
    public function exportRows(School $school, string $from, string $to, ?StudyGroup $group = null): Collection
    {
        return DB::table('attendance_records as ar')
            ->join('attendance_sessions as asess', 'asess.id', '=', 'ar.attendance_session_id')
            ->join('students as s', 's.id', '=', 'ar.student_id')
            ->join('study_groups as sg', 'sg.id', '=', 'asess.study_group_id')
            ->where('ar.school_id', $school->getKey())
            ->whereBetween('asess.session_date', [$from, $to])
            ->when($group !== null, fn ($query) => $query->where('asess.study_group_id', $group->getKey()))
            ->selectRaw('
                asess.session_date,
                sg.name as study_group,
                s.nis,
                s.full_name,
                ar.status,
                ar.notes,
                ar.correction_count
            ')
            ->orderBy('asess.session_date')
            ->orderBy('sg.name')
            ->orderBy('s.full_name')
            ->get();
    }

    /**
     * CSV header plus rows. Exported through the controller, never written to a
     * public path.
     */
    public function toCsv(School $school, string $from, string $to, ?StudyGroup $group = null): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Tanggal', 'Rombel', 'NIS', 'Nama', 'Status', 'Koreksi', 'Catatan']);

        foreach ($this->exportRows($school, $from, $to, $group) as $row) {
            fputcsv($handle, [
                $row->session_date,
                $row->study_group,
                $row->nis,
                $row->full_name,
                AttendanceStatus::from($row->status)->label(),
                $row->correction_count,
                $row->notes,
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, int>
     */
    private function countsByStatus(Collection $rows): array
    {
        $counts = [];

        foreach (AttendanceStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($rows as $row) {
            // The model casts `status` to an enum, but a raw aggregate selects
            // the column as a string, so both shapes have to be accepted.
            $value = $row->status instanceof AttendanceStatus
                ? $row->status->value
                : (string) $row->status;

            if (isset($counts[$value])) {
                $counts[$value] += (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{total: int, counts: array<string, int>, attendance_rate: ?float, effective_attendance_rate: ?float}
     */
    private function summarise(array $counts): array
    {
        $total = array_sum($counts);

        $present = $counts[AttendanceStatus::Present->value] ?? 0;
        $excused = 0;

        foreach (AttendanceStatus::excused() as $status) {
            $excused += $counts[$status->value] ?? 0;
        }

        return [
            'total' => $total,
            'counts' => $counts,
            'attendance_rate' => $total === 0 ? null : round($present / $total * 100, 2),
            'effective_attendance_rate' => $total === 0
                ? null
                : round(($present + $excused) / $total * 100, 2),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function pivot(Collection $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $key = (string) $row->study_group_id;

            $result[$key] ??= [
                'study_group_id' => (int) $row->study_group_id,
                'study_group' => $row->study_group_name,
                'total' => 0,
            ];

            $result[$key][$row->status] = (int) $row->aggregate;
            $result[$key]['total'] += (int) $row->aggregate;
        }

        foreach ($result as $key => $group) {
            $counts = [];

            foreach (AttendanceStatus::cases() as $status) {
                $counts[$status->value] = $group[$status->value] ?? 0;
            }

            $result[$key] = $group + $this->summarise($counts);
        }

        return $result;
    }
}
