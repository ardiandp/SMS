<?php

namespace App\Domain\Attendance;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marking and correcting attendance.
 *
 * Two paths, deliberately separate:
 *  - mark()      edits an OPEN session. Cheap, no correction trail needed.
 *  - correct()   edits a FINALIZED session. Refuses to change a status that is
 *                already the target, and always records previous status, actor
 *                and time plus an audit event, as PRD-03 requires.
 */
class AttendanceRecordService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * Apply a bulk status change to an open session.
     *
     * @param  array<int|string, AttendanceStatus>  $statusesByRecord  record id => status
     * @return int number of records changed
     */
    public function markMany(
        AttendanceSession $session,
        array $statusesByRecord,
        User $actor,
        ?string $note = null,
    ): int {
        if ($session->isLocked()) {
            throw ValidationException::withMessages([
                'session' => 'Sesi absensi sudah selesai. Gunakan jalur koreksi untuk mengubahnya.',
            ]);
        }

        return DB::transaction(function () use ($session, $statusesByRecord, $actor, $note): int {
            $changed = 0;

            foreach ($statusesByRecord as $recordId => $status) {
                $record = $session->records()->whereKey($recordId)->first();

                if ($record === null || $record->status === $status) {
                    continue;
                }

                $record->forceFill([
                    'status' => $status,
                    'notes' => $note,
                    'marked_by' => $actor->getKey(),
                    'marked_at' => now(),
                ])->save();

                $changed++;
            }

            if ($changed > 0) {
                $this->audit->record(
                    action: 'attendance_records.marked',
                    school: $this->currentSchool->school(),
                    actor: $actor,
                    subject: $session,
                    metadata: [
                        'attendance_session_id' => $session->getKey(),
                        'changed_count' => $changed,
                    ],
                );
            }

            return $changed;
        });
    }

    /**
     * Set every record in the session to one status.
     */
    public function markAll(
        AttendanceSession $session,
        AttendanceStatus $status,
        User $actor,
        ?string $note = null,
    ): int {
        $statuses = $session->records()
            ->where('status', '!=', $status->value)
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [$id => $status])
            ->all();

        return $this->markMany($session, $statuses, $actor, $note);
    }

    /**
     * Change one record on a finalized session, keeping an audit trail.
     */
    public function correct(
        AttendanceRecord $record,
        AttendanceStatus $status,
        User $actor,
        string $reason,
    ): AttendanceRecord {
        if ($record->status === $status) {
            throw ValidationException::withMessages([
                'status' => 'Status absensi sudah bernilai tersebut.',
            ]);
        }

        return DB::transaction(function () use ($record, $status, $actor, $reason): AttendanceRecord {
            $previous = $record->status;

            $record->forceFill([
                'status' => $status,
                'previous_status' => $previous->value,
                'corrected_by' => $actor->getKey(),
                'corrected_at' => now(),
                'correction_count' => $record->correction_count + 1,
            ])->save();

            $this->audit->record(
                action: 'attendance_record.corrected',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $record,
                metadata: [
                    'attendance_record_id' => $record->getKey(),
                    'student_id' => $record->student_id,
                    'from' => $previous->value,
                    'to' => $status->value,
                    'reason' => $reason,
                ],
            );

            return $record;
        });
    }

    /**
     * Add or replace a student on an open session (late enrolment, transfer in).
     */
    public function attachStudent(AttendanceSession $session, Student $student, AttendanceStatus $status, User $actor): AttendanceRecord
    {
        if ($session->isLocked()) {
            throw ValidationException::withMessages([
                'session' => 'Sesi absensi sudah selesai dan tidak dapat menambah siswa.',
            ]);
        }

        return DB::transaction(function () use ($session, $student, $status, $actor): AttendanceRecord {
            $record = AttendanceRecord::firstOrCreate(
                [
                    'attendance_session_id' => $session->getKey(),
                    'student_id' => $student->getKey(),
                ],
                [
                    'school_id' => $session->school_id,
                    'status' => $status,
                    'marked_by' => $actor->getKey(),
                    'marked_at' => now(),
                ],
            );

            if (! $record->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'student_id' => 'Siswa sudah memiliki catatan pada sesi ini.',
                ]);
            }

            $this->audit->record(
                action: 'attendance_record.attached',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $record,
                metadata: [
                    'attendance_session_id' => $session->getKey(),
                    'student_id' => $student->getKey(),
                ],
            );

            return $record;
        });
    }
}
