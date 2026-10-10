<?php

namespace App\Policies;

use App\Domain\Attendance\TeacherAssignmentResolver;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class AssessmentPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly TeacherAssignmentResolver $teacherAssignments,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.grades');
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->owns($assessment) && $user->can('view.grades');
    }

    /**
     * A teacher may create assessments only for assignments they teach;
     * `manage.grading-policies` marks full administrative rights over grading.
     */
    public function create(User $user): bool
    {
        return $this->inSchool()
            && ($user->can('manage.grading-policies') || $user->can('manage.assessments'));
    }

    public function update(User $user, Assessment $assessment): bool
    {
        if (! $this->owns($assessment) || $assessment->status !== AssessmentStatus::Draft) {
            return false;
        }

        if ($user->can('manage.grading-policies')) {
            return true;
        }

        return $user->can('manage.assessments')
            && $this->teacherAssignments->teachesAssignment($user, $assessment->teachingAssignment);
    }

    /**
     * Publishing an assessment finalizes every score it contains — an
     * administrative act, not a teacher's.
     */
    public function publish(User $user, Assessment $assessment): bool
    {
        return $this->owns($assessment)
            && $assessment->status === AssessmentStatus::Draft
            && $user->can('finalize.grades');
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        if (! $this->owns($assessment) || $assessment->status !== AssessmentStatus::Draft) {
            return false;
        }

        if ($user->can('manage.grading-policies')) {
            return true;
        }

        return $user->can('manage.assessments')
            && $this->teacherAssignments->teachesAssignment($user, $assessment->teachingAssignment);
    }

    /**
     * Correcting a finalized number is the strongest act in the grading
     * domain; it requires the dedicated permission.
     */
    public function correct(User $user, Assessment $assessment): bool
    {
        return $this->owns($assessment)
            && $assessment->status === AssessmentStatus::Published
            && $user->can('correct.grades');
    }
}
