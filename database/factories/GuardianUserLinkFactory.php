<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\GuardianUserLink;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianUserLink>
 */
class GuardianUserLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'guardian_id' => Guardian::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function linking(Guardian $guardian, User $user): static
    {
        return $this->state(fn (): array => [
            'school_id' => $guardian->school_id,
            'guardian_id' => $guardian->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }
}
