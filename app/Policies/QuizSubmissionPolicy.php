<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Domain\Academics\QuizSubmission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class QuizSubmissionPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.quiz-submissions');
    }

    public function view(User $user, QuizSubmission $submission): bool
    {
        return $this->owns($submission) && $user->can('view.quiz-submissions');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('submit.quiz-submission');
    }

    public function update(User $user, QuizSubmission $submission): bool
    {
        // Only the student who owns the submission may update their own draft.
        return $this->owns($submission)
            && $user->can('submit.quiz-submission')
            && $submission->status === 'draft';
    }

    public function submit(User $user, QuizSubmission $submission): bool
    {
        return $this->owns($submission)
            && $user->can('submit.quiz-submission')
            && $submission->status === 'draft';
    }

    public function grade(User $user, QuizSubmission $submission): bool
    {
        return $this->owns($submission) && $user->can('grade.quiz-submission');
    }

    public function delete(User $user, QuizSubmission $submission): bool
    {
        // Only the student who owns the submission may delete their own draft.
        return $this->owns($submission)
            && $user->can('submit.quiz-submission')
            && $submission->status === 'draft';
    }
}