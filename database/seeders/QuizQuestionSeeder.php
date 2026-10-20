<?php

namespace Database\Seeders;

use App\Models\Domain\Academics\Quiz;
use App\Models\Domain\Academics\QuizQuestion;
use Illuminate\Database\Seeder;

class QuizQuestionSeeder extends Seeder
{
    public function run(): void
    {
        Quiz::factory()->count(3)->each(function (Quiz $quiz) {
            QuizQuestion::factory()->forQuiz($quiz)->count(4)->create(['is_active' => true]);
            QuizQuestion::factory()->forQuiz($quiz)->count(1)->inactive()->create(['is_active' => false]);
        });

        $this->command->info('Quiz questions created.');
    }
}
