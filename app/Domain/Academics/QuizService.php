<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\Domain\Academics\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quiz header (grouping of questions) for the LMS module. */
class QuizService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $guard,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Quiz
    {
        return DB::transaction(function () use ($data, $actor): Quiz {
            $quiz = new Quiz($data);
            $quiz->assignToCurrentSchool();

            $this->guard->assertRelated(
                [$quiz->academicYear, $quiz->subject, $quiz->studyGroup],
                (int) $quiz->school_id,
                ['school_id'],
            );

            if (! isset($data['title']) || trim($data['title']) === '') {
                throw ValidationException::withMessages([
                    'title' => 'Judul kuis wajib diisi.',
                ]);
            }

            $quiz->created_by = $actor->id;
            $quiz->updated_by = $actor->id;
            $quiz->save();

            $this->audit->record(
                action: 'quiz.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $quiz,
                metadata: ['quiz_id' => $quiz->getKey()],
            );

            return $quiz;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Quiz $quiz, array $data, User $actor): Quiz
    {
        return DB::transaction(function () use ($quiz, $data, $actor): Quiz {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $quiz, ['school_id']);

            if (isset($data['title']) && trim($data['title']) === '') {
                throw ValidationException::withMessages([
                    'title' => 'Judul kuis wajib diisi.',
                ]);
            }

            $quiz->fill($data);
            unset($quiz->school_id);
            $quiz->updated_by = $actor->id;
            $quiz->save();

            $this->audit->record(
                action: 'quiz.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $quiz,
                metadata: ['quiz_id' => $quiz->getKey()],
            );

            return $quiz;
        });
    }

    public function delete(Quiz $quiz, User $actor): void
    {
        DB::transaction(function () use ($quiz, $actor): void {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $quiz, ['school_id']);

            $quiz->is_active = false;
            $quiz->updated_by = $actor->id;
            $quiz->save();

            $this->audit->record(
                action: 'quiz.deleted',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $quiz,
                metadata: ['quiz_id' => $quiz->getKey()],
            );
        });
    }

    public function restore(Quiz $quiz, User $actor): void
    {
        DB::transaction(function () use ($quiz, $actor): void {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $quiz, ['school_id']);

            $quiz->is_active = true;
            $quiz->updated_by = $actor->id;
            $quiz->save();

            $this->audit->record(
                action: 'quiz.restored',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $quiz,
                metadata: ['quiz_id' => $quiz->getKey()],
            );
        });
    }

    public function getActive(int $schoolId): Builder
    {
        return Quiz::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('subject_id')
            ->orderBy('academic_year_id')
            ->orderBy('created_at');
    }
}
