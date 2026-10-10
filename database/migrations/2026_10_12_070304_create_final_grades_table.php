<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * calculation_detail_json stores the per-category breakdown so a published
     * grade can always be explained without re-running the calculation.
     */
    public function up(): void
    {
        Schema::create('final_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->decimal('numeric_score', 5, 2)->nullable();
            $table->string('grade_label', 5)->nullable();
            $table->string('calculation_version', 100)->nullable();
            $table->json('calculation_detail_json')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'teaching_assignment_id', 'semester_id'],
                'final_grades_unique_student_assignment_semester'
            );

            $table->index(['school_id', 'semester_id', 'student_id']);
        });

        DB::statement(
            'ALTER TABLE `final_grades` ADD CONSTRAINT `final_grades_score_range` CHECK (`numeric_score` IS NULL OR (`numeric_score` >= 0 AND `numeric_score` <= 100))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('final_grades');
    }
};