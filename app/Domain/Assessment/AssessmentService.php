<?php

namespace App\Domain\Assessment;

use App\Domain\Academics\TenantConsistencyGuard;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Semester;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assessment lifecycle (PRD-04).
 *
 * An assessment is created as a draft, collects raw scores while it is a
 * draft, and is published once. Publishing finalizes every entered score;
 * after that the only way to change a number is a correction that keeps the
 * actor, the time and the previous value.
 */
class AssessmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $guard,
        private readonly AssessmentScoreService $scores,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Assessment
    {
        return DB::transaction(function () use ($data, $actor): Assessment {
            $assessment = new Assessment($data);
            $assessment->assignToCurrentSchool();
            $assessment->status = AssessmentStatus::Draft;

            $schoolId = (int) $assessment->school_id;

            $this->guard->assertRelated(
                [$assessment->teachingAssignment, $assessment->category],
                $schoolId,
                ['school_id'],
            );

            if ($assessment->teachingAssignment === null) {
                throw ValidationException::withMessages([
                    'teaching_assignment_id' => 'Penugasan mengajar wajib dipilih.',
                ]);
            }

            // The semester defaults to the assignment's so a score can always be
            // traced back to the period it belongs to.
            $assessment->semester_id ??= $assessment->teachingAssignment->semester_id;

            $assessment->save();

            $this->audit->record(
                action: 'assessment.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $assessment,
                metadata: [
                    'assessment_id' => $assessment->getKey(),
                    'teaching_assignment_id' => $assessment->teaching_assignment_id,
                ],
            );

            return $assessment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Assessment $assessment, array $data, User $actor): Assessment
    {
        return DB::transaction(function () use ($assessment, $data, $actor): Assessment {
            if ($assessment->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Asesmen yang sudah diterbitkan tidak dapat diubah. Gunakan koreksi nilai.',
                ]);
            }

            $assessment->fill($data);

            $this->guard->assertRelated(
                [$assessment->teachingAssignment, $assessment->category],
                (int) $assessment->school_id,
                ['school_id'],
            );

            $assessment->save();

            $this->audit->record(
                action: 'assessment.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $assessment,
                metadata: ['assessment_id' => $assessment->getKey()],
            );

            return $assessment;
        });
    }

    /**
     * Publish an assessment and finalize every entered score.
     *
     * Publishing with no scores at all is rejected: an empty publication would
     * freeze nothing and confuse the final grade calculation.
     */
    public function publish(Assessment $assessment, User $actor): Assessment
    {
        return DB::transaction(function () use ($assessment, $actor): Assessment {
            if ($assessment->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Asesmen sudah diterbitkan.',
                ]);
            }

            if (! $this->scores->hasAnyScore($assessment)) {
                throw ValidationException::withMessages([
                    'status' => 'Belum ada nilai yang diisi. Isi nilai sebelum menerbitkan asesmen.',
                ]);
            }

            $assessment->status = AssessmentStatus::Published;
            $assessment->save();

            $this->scores->finalizeScores($assessment);

            $this->audit->record(
                action: 'assessment.published',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $assessment,
                metadata: ['assessment_id' => $assessment->getKey()],
            );

            return $assessment;
        });
    }

    /**
     * Delete a draft. A published assessment is history and is kept.
     */
    public function delete(Assessment $assessment, User $actor): void
    {
        DB::transaction(function () use ($assessment, $actor): void {
            if ($assessment->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Asesmen yang sudah diterbitkan tidak dapat dihapus.',
                ]);
            }

            $this->audit->record(
                action: 'assessment.deleted',
                school: $this->currentSchool->school(),
                actor: $actor,
                metadata: [
                    'assessment_id' => $assessment->getKey(),
                    'title' => $assessment->title,
                ],
            );

            $assessment->delete();
        });
    }

    public function assertCategoryBelongsToTenant(?AssessmentCategory $category): void
    {
        if ($category !== null) {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $category, ['school_id']);
        }
    }

    public function assertSemesterBelongsToAssignment(?Semester $semester, TeachingAssignment $assignment): void
    {
        if ($semester !== null && $assignment->semester_id !== null
            && (int) $semester->getKey() !== (int) $assignment->semester_id) {
            throw ValidationException::withMessages([
                'semester_id' => 'Semester harus sama dengan semester pada penugasan mengajar.',
            ]);
        }
    }
}
