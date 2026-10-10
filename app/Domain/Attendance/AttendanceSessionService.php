<?php

namespace App\Domain\Attendance;

use App\Domain\Academics\TenantConsistencyGuard;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\StudyGroupStatus;
use App\Models\AttendanceSession;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates attendance sessions and manages their lifecycle.
 *
 * Constraint not expressible in the schema: creating a session must also create
 * exactly one record per student on the roster for that date. The unique index
 * on (attendance_session_id, student_id) is the safety net, and a driver
 * violation is translated into a validation message instead of a 500.
 */
class AttendanceSessionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly StudyGroupRoster $roster,
        private readonly TenantConsistencyGuard $guard,
    ) {}

    /**
     * Open a session for a study group on a date, seeding one record per
     * student on the roster as of that date.
     */
    public function open(
        StudyGroup $group,
        string $sessionDate,
        User $actor,
        AttendanceStatus $defaultStatus = AttendanceStatus::Present,
    ): AttendanceSession {
        return DB::transaction(function () use ($group, $sessionDate, $actor, $defaultStatus): AttendanceSession {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $group, ['school_id']);

            if ($group->status === StudyGroupStatus::Archived) {
                throw ValidationException::withMessages([
                    'study_group_id' => 'Rombel sudah diarsipkan dan tidak dapat dipakai untuk absensi.',
                ]);
            }

            $existing = AttendanceSession::query()
                ->where('study_group_id', $group->getKey())
                ->where('session_date', $sessionDate)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'session_date' => 'Sesi absensi untuk rombel ini pada tanggal tersebut sudah ada.',
                ]);
            }

            $session = new AttendanceSession([
                'study_group_id' => $group->getKey(),
                'session_date' => $sessionDate,
                'session_type' => 'daily',
                'teaching_assignment_id' => null,
                'status' => AttendanceSessionStatus::Open,
                'created_by' => $actor->getKey(),
            ]);
            $session->assignToCurrentSchool();

            try {
                $session->save();
            } catch (QueryException $exception) {
                if ($this->isUniqueViolation($exception)) {
                    throw ValidationException::withMessages([
                        'session_date' => 'Sesi absensi untuk rombel ini pada tanggal tersebut sudah ada.',
                    ]);
                }

                throw $exception;
            }

            $this->seedRecords($session, $defaultStatus, $actor);

            $this->audit->record(
                action: 'attendance_session.opened',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $session,
                metadata: [
                    'attendance_session_id' => $session->getKey(),
                    'student_count' => $session->records()->count(),
                ],
            );

            return $session;
        });
    }

    /**
     * Lock a session so further edits become auditable corrections.
     */
    public function finalize(AttendanceSession $session, User $actor): AttendanceSession
    {
        return DB::transaction(function () use ($session, $actor): AttendanceSession {
            $session->status = AttendanceSessionStatus::Finalized;
            $session->save();

            $this->audit->record(
                action: 'attendance_session.finalized',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $session,
                metadata: ['attendance_session_id' => $session->getKey()],
            );

            return $session;
        });
    }

    /**
     * Reopen a finalized session. Only for privileged roles, always audited.
     */
    public function reopen(AttendanceSession $session, User $actor, string $reason): AttendanceSession
    {
        return DB::transaction(function () use ($session, $actor, $reason): AttendanceSession {
            $session->status = AttendanceSessionStatus::Open;
            $session->save();

            $this->audit->record(
                action: 'attendance_session.reopened',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $session,
                metadata: [
                    'attendance_session_id' => $session->getKey(),
                    'reason' => $reason,
                ],
            );

            return $session;
        });
    }

    /**
     * @return int number of records created
     */
    private function seedRecords(AttendanceSession $session, AttendanceStatus $status, User $actor): int
    {
        $group = $session->studyGroup;

        $studentIds = $this->roster->studentIdsOn($group, $session->session_date->toDateString());

        if ($studentIds === []) {
            return 0;
        }

        $rows = array_map(fn (int $studentId): array => [
            'school_id' => $session->school_id,
            'attendance_session_id' => $session->getKey(),
            'student_id' => $studentId,
            'status' => $status->value,
            'notes' => null,
            'marked_by' => $actor->getKey(),
            'marked_at' => now(),
            'previous_status' => null,
            'corrected_by' => null,
            'corrected_at' => null,
            'correction_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $studentIds);

        DB::table('attendance_records')->insert($rows);

        return count($rows);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || str_contains($exception->getMessage(), 'Duplicate entry');
    }
}
