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
     * Raw scores live here; the computed result lives in final_grades. Keeping
     * them apart is what lets a policy change be re-run without losing what the
     * teacher originally entered.
     */
    public function up(): void
    {
        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();

            // Correction trail, mirroring attendance_records: PRD-04 requires a
            // change to a finalized score to keep actor, time and old value.
            $table->decimal('previous_score', 6, 2)->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->unsignedSmallInteger('correction_count')->default(0);

            $table->timestamps();

            $table->unique(['assessment_id', 'student_id'], 'assessment_scores_unique_student');
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'assessment_id']);
        });

        // A score may be empty (not yet entered) but never negative.
        DB::statement(
            'ALTER TABLE `assessment_scores` ADD CONSTRAINT `assessment_scores_not_negative` CHECK (`score` IS NULL OR `score` >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_scores');
    }
};