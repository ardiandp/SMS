<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Domain\Academics\QuizQuestion;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class QuizQuestionPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.quiz-questions');
    }

    public function view(User $user, QuizQuestion $quizQuestion): bool
    {
        return $this->owns($quizQuestion) && $user->can('view.quiz-questions');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.quiz-questions');
    }

    public function update(User $user, QuizQuestion $quizQuestion): bool
    {
        return $this->owns($quizQuestion) && $user->can('manage.quiz-questions');
    }

    public function delete(User $user, QuizQuestion $quizQuestion): bool
    {
        return $this->owns($quizQuestion) && $user->can('manage.quiz-questions');
    }

    public function restore(User $user, QuizQuestion $quizQuestion): bool
    {
        return $this->owns($quizQuestion) && $user->can('manage.quiz-questions');
    }

    public function forceDelete(User $user, QuizQuestion $quizQuestion): bool
    {
        return $this->owns($quizQuestion) && $user->can('manage.quiz-questions');
    }
}
