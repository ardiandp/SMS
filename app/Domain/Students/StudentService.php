<?php

namespace App\Domain\Students;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\StudentStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * Create a student bound to the current tenant, with initial status history.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Student
    {
        return DB::transaction(function () use ($data, $actor): Student {
            $student = new Student($data);
            $student->assignToCurrentSchool();
            $student->save();

            $this->recordStatus($student, null, $student->status->value, $actor);

            $this->audit->record(
                action: 'student.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $student,
                metadata: ['student_id' => $student->getKey()],
            );

            return $student;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data, User $actor): Student
    {
        return DB::transaction(function () use ($student, $data, $actor): Student {
            $previousStatus = $student->status->value;

            $student->fill($data);
            $student->save();

            if ($student->status->value !== $previousStatus) {
                $this->recordStatus($student, $previousStatus, $student->status->value, $actor);
            }

            $this->audit->record(
                action: 'student.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $student,
                metadata: ['student_id' => $student->getKey()],
            );

            return $student;
        });
    }

    public function changeStatus(
        Student $student,
        StudentStatus $status,
        ?string $reason,
        User $actor,
    ): Student {
        return DB::transaction(function () use ($student, $status, $reason, $actor): Student {
            $previousStatus = $student->status->value;

            $student->status = $status;
            $student->save();

            $this->recordStatus($student, $previousStatus, $status->value, $actor, $reason);

            $this->audit->record(
                action: 'student.status_changed',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $student,
                metadata: [
                    'student_id' => $student->getKey(),
                    'from' => $previousStatus,
                    'to' => $status->value,
                ],
            );

            return $student;
        });
    }

    /**
     * Deactivate (soft delete) a student and keep the history trail consistent.
     */
    public function deactivate(Student $student, User $actor): Student
    {
        return DB::transaction(function () use ($student, $actor): Student {
            $previous = $student->status->value;

            $student->status = StudentStatus::Inactive;
            $student->save();
            $student->delete();

            $this->recordStatus($student, $previous, StudentStatus::Inactive->value, $actor, 'Siswa dinonaktifkan.');

            $this->audit->record(
                action: 'student.deactivated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $student,
                metadata: [
                    'student_id' => $student->getKey(),
                    'from' => $previous,
                    'to' => StudentStatus::Inactive->value,
                ],
            );

            return $student;
        });
    }

    /**
     * Attach a guardian after verifying both sides belong to the same tenant.
     */
    public function linkGuardian(
        Student $student,
        Guardian $guardian,
        string $relationship,
        User $actor,
    ): StudentGuardian {
        $schoolId = $this->currentSchool->school()?->getKey();

        abort_unless(
            $student->school_id === $schoolId && $guardian->school_id === $schoolId,
            403,
        );

        return DB::transaction(function () use ($student, $guardian, $relationship, $actor): StudentGuardian {
            $pivot = StudentGuardian::firstOrCreate(
                [
                    'student_id' => $student->getKey(),
                    'guardian_id' => $guardian->getKey(),
                ],
                [
                    'school_id' => $student->school_id,
                    'relationship' => $relationship,
                ],
            );

            $this->audit->record(
                action: 'student.guardian_linked',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $student,
                metadata: [
                    'student_id' => $student->getKey(),
                    'guardian_id' => $guardian->getKey(),
                ],
            );

            return $pivot;
        });
    }

    private function recordStatus(
        Student $student,
        ?string $from,
        string $to,
        User $actor,
        ?string $reason = null,
    ): void {
        StudentStatusHistory::create([
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by' => $actor->getKey(),
        ]);
    }
}
