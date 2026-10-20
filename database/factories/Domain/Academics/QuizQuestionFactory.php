<?php

namespace Database\Factories\Domain\Academics;

use App\Models\Domain\Academics\QuizQuestion;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Subject;
use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    public function definition(): array
    {
        $choices = [];
        for ($i = 0; $i < 3; $i++) {
            $key = ['A', 'B', 'C', 'D', 'E', 'F'][$i];
            $choices[$key] = fake()->words(3, true);
        }
        $correct = array_rand($choices);
        $correctIndex = array_search($correct, array_keys($choices));

        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'subject_id' => Subject::factory(),
            'study_group_id' => StudyGroup::factory(),
            'question_text' => fake()->unique()->sentence(8, true),
            'choices' => $choices,
            'correct_choice_index' => $correctIndex,
            'difficulty' => 'medium',
            'question_type' => 'multiple_choice',
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Bind all FKs to one tenant so the factory never produces a
     * cross-tenant relation by accident. Overrides (e.g. school_id) passed
     * to create() still win because state() applies after definition().
     */
    public function configure(): static
    {
        return $this->afterMaking(function (QuizQuestion $question): void {
            $schoolId = $question->school_id
                ?? School::first()?->getKey()
                ?? School::factory()->create()->getKey();

            $question->school_id = $schoolId;

            foreach (['academicYear' => AcademicYear::class, 'subject' => Subject::class, 'studyGroup' => StudyGroup::class] as $relation => $class) {
                $fk = match ($relation) {
                    'academicYear' => 'academic_year_id',
                    'subject' => 'subject_id',
                    'studyGroup' => 'study_group_id',
                };

                $related = $relation ? $question->getRelationValue($relation) : null;

                if ($related === null && empty($question->getAttributes()[$fk] ?? null)) {
                    $question->{$fk} = $class::factory()->create(['school_id' => $schoolId])->getKey();
                } elseif (! empty($question->getAttributes()[$fk] ?? null)) {
                    // An explicit FK override wins; coerce its owner school when
                    // the caller only overrode school_id.
                    $owner = $class::withoutGlobalScopes()->find($question->getAttributes()[$fk]);
                    if ($owner !== null && (int) $owner->school_id !== (int) $schoolId) {
                        $question->{$fk} = $class::factory()->create(['school_id' => $schoolId])->getKey();
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

    public function easy(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => 'easy',
        ]);
    }

    public function hard(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => 'hard',
        ]);
    }
}