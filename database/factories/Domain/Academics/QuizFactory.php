<?php

namespace Database\Factories\Domain\Academics;

use App\Models\Domain\Academics\Quiz;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Subject;
use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'subject_id' => Subject::factory(),
            'study_group_id' => StudyGroup::factory(),
            'title' => fake()->unique()->sentence(4, true),
            'description' => fake()->optional()->sentence(12, true),
            'time_limit' => fake()->optional()->numberBetween(10, 120),
            'shuffle_questions' => fake()->boolean(),
            'shuffle_choices' => fake()->boolean(),
            'passing_score' => 70,
            'is_active' => true,
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
        return $this->afterMaking(function (Quiz $quiz): void {
            $schoolId = $quiz->school_id
                ?? School::first()?->getKey()
                ?? School::factory()->create()->getKey();

            $quiz->school_id = $schoolId;

            foreach (['academicYear' => AcademicYear::class, 'subject' => Subject::class, 'studyGroup' => StudyGroup::class] as $relation => $class) {
                $fk = match ($relation) {
                    'academicYear' => 'academic_year_id',
                    'subject' => 'subject_id',
                    'studyGroup' => 'study_group_id',
                };

                $related = $relation ? $quiz->getRelationValue($relation) : null;

                if ($related === null && empty($quiz->getAttributes()[$fk] ?? null)) {
                    $quiz->{$fk} = $class::factory()->create(['school_id' => $schoolId])->getKey();
                } elseif (! empty($quiz->getAttributes()[$fk] ?? null)) {
                    $owner = $class::withoutGlobalScopes()->find($quiz->getAttributes()[$fk]);
                    if ($owner !== null && (int) $owner->school_id !== (int) $schoolId) {
                        $quiz->{$fk} = $class::factory()->create(['school_id' => $schoolId])->getKey();
                    }
                }
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
