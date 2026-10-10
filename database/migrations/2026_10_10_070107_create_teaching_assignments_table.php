<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('study_group_id')->constrained('study_groups')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['school_id', 'study_group_id']);
            $table->index(['school_id', 'employee_id']);
            $table->index(['school_id', 'subject_id']);
            $table->index(['school_id', 'semester_id']);

            // One teacher holds a given subject in a given study group per semester.
            // Rows without a semester are whole-year assignments and are left out
            // of the constraint so they are checked by the service instead.
            $table->unique(['study_group_id', 'subject_id', 'employee_id', 'semester_id'], 'teaching_assignments_unique_per_semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
