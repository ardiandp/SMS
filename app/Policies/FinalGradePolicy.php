<?php

namespace App\Policies;

use App\Domain\Attendance\TeacherAssignmentResolver;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\FinalGrade;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class FinalGradePolicy
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

    public function view(User $user, FinalGrade $grade): bool
    {
        return $this->owns($grade) && $user->can('view.grades');
    }

    /**
     * Running the calculation writes draft grades for a whole assignment:
     * an administrative act.
     */
    public function calculate(User $user): bool
    {
        return $this->inSchool()
            && ($user->can('manage.grading-policies') || $user->can('finalize.grades'));
    }

    /**
     * Finalizing stops recalculation; administrative act.
     */
    public function finalize(User $user, FinalGrade $grade): bool
    {
        return $this->owns($grade) && $user->can('finalize.grades');
    }

    /**
     * Correcting a finalized number requires the dedicated permission.
     */
    public function correct(User $user, FinalGrade $grade): bool
    {
        return $this->owns($grade) && $user->can('correct.grades');
    }
}
