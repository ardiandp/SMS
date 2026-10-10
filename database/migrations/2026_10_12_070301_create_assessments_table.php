<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('assessment_categories')->nullOnDelete();
            $table->string('title', 191);
            $table->date('assessment_date');
            $table->decimal('max_score', 6, 2)->default(100);
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'semester_id']);
            $table->index(['teaching_assignment_id', 'assessment_date']);
            $table->index(['school_id', 'status']);
        });

        // max_score must stay positive or a percentage cannot be computed.
        DB::statement(
            'ALTER TABLE `assessments` ADD CONSTRAINT `assessments_max_score_positive` CHECK (`max_score` > 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};