<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Domain\Academics\Quiz;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class QuizPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.quizzes');
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $this->owns($quiz) && $user->can('view.quizzes');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.quizzes');
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $this->owns($quiz) && $user->can('manage.quizzes');
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->owns($quiz) && $user->can('manage.quizzes');
    }

    public function restore(User $user, Quiz $quiz): bool
    {
        return $this->owns($quiz) && $user->can('manage.quizzes');
    }
}