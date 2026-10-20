<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\Domain\Academics\Quiz;
use App\Models\Domain\Academics\QuizSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quiz submission (jawaban siswa) management for the LMS module. */
class QuizSubmissionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly TenantConsistencyGuard $guard,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): QuizSubmission
    {
        return DB::transaction(function () use ($data, $actor): QuizSubmission {
            $submission = new QuizSubmission($data);
            $submission->assignToCurrentSchool();

            // The quiz and student must both belong to the active school.
            $this->guard->assertSameSchool(
                (int) $submission->school_id,
                $submission->quiz,
                ['school_id'],
            );
            $this->guard->assertSameSchool(
                (int) $submission->school_id,
                $submission->student,
                ['school_id'],
            );

            if (! isset($data['answers']) || ! is_array($data['answers'])) {
                throw ValidationException::withMessages([
                    'answers' => 'Jawaban wajib diisi.',
                ]);
            }

            $submission->status = 'draft';
            $submission->created_by = $actor->id;
            $submission->updated_by = $actor->id;
            $submission->save();

            $this->audit->record(
                action: 'quiz_submission.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $submission,
                metadata: ['quiz_submission_id' => $submission->getKey()],
            );

            return $submission;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(QuizSubmission $submission, array $data, User $actor): QuizSubmission
    {
        return DB::transaction(function () use ($submission, $data, $actor): QuizSubmission {
            // Ownership is checked against the ACTIVE tenant, not the record
            // itself — comparing the record to itself would always pass.
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $submission, ['school_id']);

            // Only draft submissions can be updated by the student.
            if ($submission->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya submission yang masih draft yang dapat diubah.',
                ]);
            }

            if (isset($data['answers']) && ! is_array($data['answers'])) {
                throw ValidationException::withMessages([
                    'answers' => 'Jawaban wajib berupa array.',
                ]);
            }

            $submission->fill($data);
            unset($submission->school_id);
            $submission->updated_by = $actor->id;
            $submission->save();

            $this->audit->record(
                action: 'quiz_submission.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $submission,
                metadata: ['quiz_submission_id' => $submission->getKey()],
            );

            return $submission;
        });
    }

    /**
     * Grade a submission. Only teachers/admins can do this.
     *
     * @param array<string, mixed> $data
     */
    public function grade(QuizSubmission $submission, array $data, User $actor): void
    {
        DB::transaction(function () use ($submission, $data, $actor): void {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $submission, ['school_id']);

            if ($submission->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya submission yang telah dikumpulkan yang dapat dinilai.',
                ]);
            }

            if (! isset($data['score']) || ! is_numeric($data['score'])) {
                throw ValidationException::withMessages([
                    'score' => 'Skor wajib diisi.',
                ]);
            }

            $score = (int) $data['score'];
            $maxScore = isset($data['max_score']) ? (int) $data['max_score'] : 100;

            if ($score < 0 || $score > $maxScore) {
                throw ValidationException::withMessages([
                    'score' => 'Skor harus antara 0 dan ' . $maxScore . '.',
                ]);
            }

            $submission->score = $score;
            $submission->max_score = $maxScore;
            $submission->status = 'graded';
            $submission->graded_by = $actor->id;
            $submission->graded_at = now();
            $submission->feedback = $data['feedback'] ?? null;
            $submission->updated_by = $actor->id;
            $submission->save();

            $this->audit->record(
                action: 'quiz_submission.graded',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $submission,
                metadata: [
                    'quiz_submission_id' => $submission->getKey(),
                    'score' => $score,
                    'max_score' => $maxScore,
                ],
            );
        });
    }

    public function delete(QuizSubmission $submission, User $actor): void
    {
        DB::transaction(function () use ($submission, $actor): void {
            $this->guard->assertSameSchool($this->guard->activeSchoolId(), $submission, ['school_id']);

            // Only draft submissions can be deleted.
            if ($submission->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya submission yang masih draft yang dapat dihapus.',
                ]);
            }

            $submission->delete();

            $this->audit->record(
                action: 'quiz_submission.deleted',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $submission,
                metadata: ['quiz_submission_id' => $submission->getKey()],
            );
        });
    }

    public function getActive(int $schoolId): Builder
    {
        return QuizSubmission::where('school_id', $schoolId)
            ->where('status', '!=', 'draft')
            ->orderBy('submitted_at', 'desc')
            ->orderBy('created_at');
    }

    /**
     * Get submissions for a specific quiz within the school.
     */
    public function getByQuiz(int $schoolId, int $quizId): Builder
    {
        return QuizSubmission::where('school_id', $schoolId)
            ->where('quiz_id', $quizId)
            ->orderBy('submitted_at', 'desc')
            ->orderBy('created_at');
    }

    /**
     * Get submissions by a specific student within the school.
     */
    public function getByStudent(int $schoolId, int $studentId): Builder
    {
        return QuizSubmission::where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc');
    }
}