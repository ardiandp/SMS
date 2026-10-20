<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('7a_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('study_group_id')->constrained('study_groups')->restrictOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('time_limit')->nullable(); // minutes
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_choices')->default(false);
            $table->unsignedTinyInteger('passing_score')->default(0); // 0-100
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'subject_id', 'study_group_id', 'title'], 'quizzes_unique');
            $table->index(['school_id', 'is_active']);
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('7a_quizzes');
    }
};