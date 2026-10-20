<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('7a_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('study_group_id')->constrained('study_groups')->restrictOnDelete();
            $table->string('question_text', 2000);
            $table->json('choices')->nullable();
            $table->unsignedTinyInteger('correct_choice_index')->default(0);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->string('question_type', 20)->default('multiple_choice');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'subject_id', 'study_group_id', 'question_text'], 'quiz_questions_unique');
            $table->index(['school_id', 'is_active']);
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('7a_quiz_questions');
    }
};
