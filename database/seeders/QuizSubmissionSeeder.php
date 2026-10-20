<?php

namespace Database\Seeders;

use App\Models\Domain\Academics\Quiz;
use App\Models\Domain\Academics\QuizSubmission;
use App\Models\Student;
use Illuminate\Database\Seeder;

class QuizSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        Quiz::where('is_active', true)->each(function (Quiz $quiz) {
            Student::where('school_id', $quiz->school_id)
                ->limit(3)
                ->each(function (Student $student) use ($quiz): void {
                    QuizSubmission::factory()
                        ->forQuizAndStudent($quiz, $student)
                        ->submitted()
                        ->create();
                });
        });

        $this->command->info('Quiz submissions created.');
    }
}
