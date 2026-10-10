<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\ClassSchedule;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Weekly lesson slots.
 *
 * Documented limitation (PRD-02 asks for this to be stated explicitly): overlap
 * detection needs a range comparison, which no MySQL/MariaDB index can express.
 * The check therefore runs in application code inside a transaction that locks
 * the candidate rows, and is covered by tests. It is correct for a single
 * application instance; a future multi-node deployment should re-check under a
 * serializable transaction or add an advisory lock.
 *
 * A conflict is raised when the same teacher, or the same room, is booked twice
 * on the same day with overlapping times.
 */
class ScheduleService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $guard,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ClassSchedule
    {
        return DB::transaction(function () use ($data, $actor): ClassSchedule {
            $schedule = new ClassSchedule($data);
            $schedule->assignToCurrentSchool();

            $assignment = $schedule->teachingAssignment;

            if ($assignment === null) {
                throw ValidationException::withMessages([
                    'teaching_assignment_id' => 'Penugasan guru wajib dipilih.',
                ]);
            }

            $this->guard->assertSameSchool((int) $schedule->school_id, $assignment, ['school_id']);

            $this->assertTimesOrdered($schedule);
            $this->assertNoConflict($schedule);

            $schedule->save();

            $this->audit->record(
                action: 'class_schedule.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $schedule,
                metadata: [
                    'class_schedule_id' => $schedule->getKey(),
                    'teaching_assignment_id' => $assignment->getKey(),
                ],
            );

            return $schedule;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClassSchedule $schedule, array $data, User $actor): ClassSchedule
    {
        return DB::transaction(function () use ($schedule, $data, $actor): ClassSchedule {
            $schedule->fill($data);

            if ($schedule->teachingAssignment === null) {
                throw ValidationException::withMessages([
                    'teaching_assignment_id' => 'Penugasan guru wajib dipilih.',
                ]);
            }

            $this->guard->assertSameSchool((int) $schedule->school_id, $schedule->teachingAssignment, ['school_id']);

            $this->assertTimesOrdered($schedule);
            $this->assertNoConflict($schedule);

            $schedule->save();

            $this->audit->record(
                action: 'class_schedule.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $schedule,
                metadata: ['class_schedule_id' => $schedule->getKey()],
            );

            return $schedule;
        });
    }

    public function delete(ClassSchedule $schedule, User $actor): void
    {
        $this->audit->record(
            action: 'class_schedule.deleted',
            school: $this->currentSchool->school(),
            actor: $actor,
            subject: $schedule,
            metadata: ['class_schedule_id' => $schedule->getKey()],
        );

        $schedule->delete();
    }

    private function assertTimesOrdered(ClassSchedule $schedule): void
    {
        if ((string) $schedule->starts_at >= (string) $schedule->ends_at) {
            throw ValidationException::withMessages([
                'ends_at' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        if (! isset(ClassSchedule::DAYS[$schedule->day_of_week])) {
            throw ValidationException::withMessages([
                'day_of_week' => 'Hari tidak valid.',
            ]);
        }

        if ($schedule->effective_from !== null
            && $schedule->effective_until !== null
            && $schedule->effective_from->greaterThan($schedule->effective_until)) {
            throw ValidationException::withMessages([
                'effective_until' => 'Tanggal berakhir harus setelah tanggal mulai berlaku.',
            ]);
        }
    }

    /**
     * Reject a slot that overlaps another slot for the same teacher or the same
     * room on the same day.
     */
    private function assertNoConflict(ClassSchedule $candidate): void
    {
        $assignment = $candidate->teachingAssignment;

        $teacherIds = TeachingAssignment::query()
            ->where('school_id', $candidate->school_id)
            ->where('employee_id', $assignment->employee_id)
            ->pluck('id');

        $conflictingScheduleIds = ClassSchedule::query()
            ->where('school_id', $candidate->school_id)
            ->where('day_of_week', $candidate->day_of_week)
            ->where('starts_at', '<', $candidate->ends_at)
            ->where('ends_at', '>', $candidate->starts_at)
            ->when(
                $candidate->exists,
                fn ($query) => $query->whereKeyNot($candidate->getKey()),
            )
            ->where(function ($query) use ($teacherIds, $candidate): void {
                $query->whereIn('teaching_assignment_id', $teacherIds);

                if ($candidate->room_label !== null && $candidate->room_label !== '') {
                    $query->orWhere('room_label', $candidate->room_label);
                }
            })
            ->lockForUpdate()
            ->pluck('id');

        if ($conflictingScheduleIds->isNotEmpty()) {
            $message = $this->describeConflict($conflictingScheduleIds);

            throw ValidationException::withMessages(['starts_at' => $message]);
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function describeConflict($ids): string
    {
        $other = ClassSchedule::query()
            ->whereIn('id', $ids->take(3))
            ->with('teachingAssignment.employee')
            ->get()
            ->first();

        if ($other === null) {
            return 'Jadwal bentrok dengan jadwal yang sudah ada.';
        }

        $employeeName = $other->teachingAssignment?->employee?->full_name ?? 'guru lain';

        return sprintf(
            'Bentrok dengan jadwal %s (%s–%s) oleh %s.',
            $other->dayLabel(),
            substr((string) $other->starts_at, 0, 5),
            substr((string) $other->ends_at, 0, 5),
            $employeeName,
        );
    }
}
