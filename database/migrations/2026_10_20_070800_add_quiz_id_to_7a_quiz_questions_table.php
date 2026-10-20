<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('7a_quiz_questions', function (Blueprint $table) {
            $table->foreignId('quiz_id')
                ->nullable()
                ->constrained('7a_quizzes')
                ->restrictOnDelete()
                ->after('id');

            $table->index(['quiz_id', 'is_active']);
            $table->dropUnique('quiz_questions_unique');
            $table->unique(['school_id', 'quiz_id', 'question_text'], 'quiz_questions_unique');
        });
    }

    public function down(): void
    {
        Schema::table('7a_quiz_questions', function (Blueprint $table) {
            $table->dropUnique('quiz_questions_unique');
            $table->dropIndex(['quiz_id', 'is_active']);
            $table->dropConstrainedForeignId('quiz_id');
            $table->unique(['school_id', 'academic_year_id', 'subject_id', 'study_group_id', 'question_text'], 'quiz_questions_unique');
        });
    }
};