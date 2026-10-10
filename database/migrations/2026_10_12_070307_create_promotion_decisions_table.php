<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Created per MIGRATION_PLAN, but deliberately NOT wired to any workflow:
     * the school has not decided the promotion rule yet (PRD-04). Nothing in
     * the application writes to this table until that decision is recorded.
     */
    public function up(): void
    {
        Schema::create('promotion_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('from_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('to_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'decision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_decisions');
    }
};