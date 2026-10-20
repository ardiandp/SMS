<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('7a_quiz_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('quiz_id')->constrained('7a_quizzes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->json('answers')->nullable(); // jawaban siswa per soal
            $table->unsignedSmallInteger('score')->nullable(); // skor yang diperoleh
            $table->unsignedSmallInteger('max_score')->nullable(); // skor maksimal
            $table->enum('status', ['draft', 'submitted', 'graded'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'quiz_id', 'student_id'], 'quiz_submissions_unique');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'quiz_id']);
            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('7a_quiz_submissions');
    }
};