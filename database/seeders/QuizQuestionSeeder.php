<?php

namespace Database\Seeders;

use App\Models\Domain\Academics\QuizQuestion;
use Illuminate\Database\Seeder;

class QuizQuestionSeeder extends Seeder
{
    public function run(): void
    {
        QuizQuestion::factory()->count(5)->create(['is_active' => true]);
        QuizQuestion::factory()->count(2)->inactive()->create(['is_active' => false]);

        $this->command->info('Quiz questions created.');
    }
}
