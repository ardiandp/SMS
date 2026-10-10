<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Matematika', 'Bahasa Indonesia', 'IPA', 'IPS', 'Pendidikan Agama',
            'PKn', 'Informatika', 'Bahasa Inggris', 'Seni Budaya', 'Olahraga',
        ]);

        return [
            'school_id' => School::factory(),
            'code' => strtoupper(substr(str_replace(' ', '', $name), 0, 3)).fake()->numberBetween(1, 99),
            'name' => $name,
            'category' => fake()->randomElement(['wajib', 'pilihan', 'muatan lokal']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
