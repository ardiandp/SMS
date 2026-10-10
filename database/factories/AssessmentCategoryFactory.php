<?php

namespace Database\Factories;

use App\Models\AssessmentCategory;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssessmentCategory>
 */
class AssessmentCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Tugas', 'Ulangan Harian', 'Ujian Tengah Semester', 'Ujian Akhir Semester', 'Praktikum',
        ]);

        return [
            'school_id' => School::factory(),
            'name' => $name,
            'slug' => Str::slug($name, '-'),
            'default_weight' => fake()->randomElement([10, 20, 30, 40]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
