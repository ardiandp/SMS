<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Domain\Academics\TenantConsistencyGuard;
use App\Models\Domain\Academics\QuizQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quiz questions (soal) management for the LMS module. */
class QuizQuestionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $guard,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): QuizQuestion
    {
        return DB::transaction(function () use ($data, $actor): QuizQuestion {
            $question = new QuizQuestion($data);
            $question->assignToCurrentSchool();

            $this->guard->assertRelated(
                [$question->academicYear, $question->subject, $question->studyGroup],
                (int) $question->school_id,
                ['school_id'],
            );

            if (! empty($data['quiz_id'])) {
                $this->guard->assertSameSchool(
                    (int) $question->school_id,
                    $question->quiz,
                    ['school_id'],
                );
            }

            foreach (['academic_year_id', 'subject_id', 'study_group_id'] as $required) {
                if (! isset($data[$required]) || $data[$required] === null) {
                    throw ValidationException::withMessages([
                        $required => 'Field wajib dipilih.',
                    ]);
                }
            }

            if (isset($data['correct_choice_index']) && $data['correct_choice_index'] >= count($data['choices'])) {
                throw ValidationException::withMessages([
                    'correct_choice_index' => 'Index jawaban benar harus ada dalam pilihan yang tersedia.',
                ]);
            }

            if (! isset($data['question_text']) || trim($data['question_text']) === '') {
                throw ValidationException::withMessages([
                    'question_text' => 'Teks soal wajib diisi.',
                ]);
            }

            $question->created_by = $actor->id;
            $question->updated_by = $actor->id;
            $question->save();

            $this->audit->record(
                action: 'quiz_question.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $question,
                metadata: ['quiz_question_id' => $question->getKey()],
            );

            return $question;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(QuizQuestion $question, array $data, User $actor): QuizQuestion
    {
        return DB::transaction(function () use ($question, $data, $actor): QuizQuestion {
            // Ownership is checked against the ACTIVE tenant, not the record
            // itself — comparing the record to itself would always pass.
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $question, ['school_id']);

            if (! empty($data['quiz_id'])) {
                $this->guard->assertSameSchool(
                    $this->guard->activeSchoolId(),
                    $question->quiz,
                    ['school_id'],
                );
            }

            foreach (['academic_year_id', 'subject_id', 'study_group_id'] as $required) {
                if (isset($data[$required]) && $data[$required] === null) {
                    throw ValidationException::withMessages([
                        $required => 'Field wajib dipilih.',
                    ]);
                }
            }

            if (isset($data['question_text']) && trim($data['question_text']) === '') {
                throw ValidationException::withMessages([
                    'question_text' => 'Teks soal wajib diisi.',
                ]);
            }

            if (isset($data['correct_choice_index']) && $data['correct_choice_index'] >= count($data['choices'])) {
                throw ValidationException::withMessages([
                    'correct_choice_index' => 'Index jawaban benar harus ada dalam pilihan yang tersedia.',
                ]);
            }

            $question->fill($data);
            unset($question->school_id);
            $question->updated_by = $actor->id;
            $question->save();

            $this->audit->record(
                action: 'quiz_question.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $question,
                metadata: ['quiz_question_id' => $question->getKey()],
            );

            return $question;
        });
    }

    public function delete(QuizQuestion $question, User $actor): void
    {
        DB::transaction(function () use ($question, $actor): void {
            // Ownership is checked against the ACTIVE tenant, not the record
            // itself — comparing the record to itself would always pass.
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $question, ['school_id']);

            $question->is_active = false;
            $question->updated_by = $actor->id;
            $question->save();

            $this->audit->record(
                action: 'quiz_question.deleted',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $question,
                metadata: ['quiz_question_id' => $question->getKey()],
            );
        });
    }

    public function restore(QuizQuestion $question, User $actor): void
    {
        DB::transaction(function () use ($question, $actor): void {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $question, ['school_id']);

            $question->is_active = true;
            $question->updated_by = $actor->id;
            $question->save();

            $this->audit->record(
                action: 'quiz_question.restored',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $question,
                metadata: ['quiz_question_id' => $question->getKey()],
            );
        });
    }

    public function getActive(int $schoolId): Builder
    {
        return QuizQuestion::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('subject_id')
            ->orderBy('academic_year_id')
            ->orderBy('created_at');
    }

    public function getBySubjectAndGroup(int $schoolId, int $subjectId, int $studyGroupId): Builder
    {
        return QuizQuestion::where('school_id', $schoolId)
            ->where('subject_id', $subjectId)
            ->where('study_group_id', $studyGroupId)
            ->where('is_active', true)
            ->orderBy('created_at');
    }

    /**
     * Get active questions belonging to a specific quiz within the school.
     */
    public function getByQuiz(int $schoolId, int $quizId): Builder
    {
        return QuizQuestion::where('school_id', $schoolId)
            ->where('quiz_id', $quizId)
            ->where('is_active', true)
            ->orderBy('created_at');
    }
}
