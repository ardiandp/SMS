<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // The name is unique per school, and the static counter only ever grows,
        // so two years created in the same test never collide.
        static $sequence = 0;

        $sequence++;
        $startsYear = 2015 + $sequence;

        return [
            'school_id' => School::factory(),
            'name' => $startsYear.'/'.($startsYear + 1),
            'starts_on' => $startsYear.'-07-01',
            'ends_on' => ($startsYear + 1).'-06-30',
            'is_active' => false,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn (): array => ['name' => $name]);
    }

    public function spanning(string $startsOn, string $endsOn): static
    {
        return $this->state(fn (): array => [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }
}
