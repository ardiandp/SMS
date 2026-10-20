<?php

namespace Database\Seeders;

use App\Models\Domain\Academics\Quiz;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        Quiz::factory()->count(3)->create(['is_active' => true]);
        Quiz::factory()->count(1)->inactive()->create(['is_active' => false]);

        $this->command->info('Quizzes created.');
    }
}
