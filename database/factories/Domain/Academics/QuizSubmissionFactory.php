<?php

namespace Database\Factories\Domain\Academics;

use App\Models\Domain\Academics\Quiz;
use App\Models\Domain\Academics\QuizSubmission;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizSubmission>
 */
class QuizSubmissionFactory extends Factory
{
    public function definition(): array
    {
        $answers = [];
        for ($i = 0; $i < fake()->numberBetween(1, 5); $i++) {
            $answers['q' . $i] = fake()->randomElement(['A', 'B', 'C', 'D']);
        }

        return [
            'school_id' => School::factory(),
            'quiz_id' => Quiz::factory(),
            'student_id' => Student::factory(),
            'answers' => $answers,
            'score' => null,
            'max_score' => null,
            'status' => 'draft',
            'submitted_at' => null,
            'graded_by' => null,
            'graded_at' => null,
            'feedback' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Bind all FKs to one tenant so the factory never produces a
     * cross-tenant relation by accident.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (QuizSubmission $submission): void {
            $schoolId = $submission->school_id
                ?? School::first()?->getKey()
                ?? School::factory()->create()->getKey();

            $submission->school_id = $schoolId;

            // Ensure quiz belongs to the same school.
            $quiz = $submission->getRelationValue('quiz');
            if ($quiz === null && empty($submission->getAttributes()['quiz_id'] ?? null)) {
                $submission->quiz_id = Quiz::factory()->create(['school_id' => $schoolId])->getKey();
            } elseif (! empty($submission->getAttributes()['quiz_id'] ?? null)) {
                $owner = Quiz::withoutGlobalScopes()->find($submission->getAttributes()['quiz_id']);
                if ($owner !== null && (int) $owner->school_id !== (int) $schoolId) {
                    $submission->quiz_id = Quiz::factory()->create(['school_id' => $schoolId])->getKey();
                }
            }

            // Ensure student belongs to the same school.
            $student = $submission->getRelationValue('student');
            if ($student === null && empty($submission->getAttributes()['student_id'] ?? null)) {
                $submission->student_id = Student::factory()->create(['school_id' => $schoolId])->getKey();
            } elseif (! empty($submission->getAttributes()['student_id'] ?? null)) {
                $owner = Student::withoutGlobalScopes()->find($submission->getAttributes()['student_id']);
                if ($owner !== null && (int) $owner->school_id !== (int) $schoolId) {
                    $submission->student_id = Student::factory()->create(['school_id' => $schoolId])->getKey();
                }
            }

            // Keep quiz and student consistent: both must belong to same school.
            foreach (['academicYear' => AcademicYear::class, 'subject' => Subject::class, 'studyGroup' => StudyGroup::class] as $relation => $class) {
                $fk = match ($relation) {
                    'academicYear' => 'academic_year_id',
                    'subject' => 'subject_id',
                    'studyGroup' => 'study_group_id',
                };

                if ($quiz !== null && ! empty($quiz->getAttributes()[$fk] ?? null)) {
                    $submission->{$fk} = $quiz->getAttributes()[$fk];
                } elseif (! empty($submission->getAttributes()[$fk] ?? null)) {
                    $owner = $class::withoutGlobalScopes()->find($submission->getAttributes()[$fk]);
                    if ($owner !== null && (int) $owner->school_id !== (int) $schoolId) {
                        $submission->{$fk} = $class::factory()->create(['school_id' => $schoolId])->getKey();
                    }
                }
            }
        });
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
            'submitted_at' => now(),
            'graded_at' => now(),
            'score' => fake()->numberBetween(60, 100),
            'max_score' => 100,
            'feedback' => fake()->sentence(),
        ]);
    }

    /**
     * Scope the submission to an existing quiz and student.
     */
    public function forQuizAndStudent(Quiz $quiz, Student $student): static
    {
        return $this->state(fn (array $attributes) => [
            'quiz_id' => $quiz->getKey(),
            'student_id' => $student->getKey(),
            'school_id' => $quiz->school_id,
        ]);
    }
}
