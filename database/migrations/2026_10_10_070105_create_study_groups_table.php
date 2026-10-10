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
        Schema::create('study_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->foreignId('homeroom_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            // Name uniqueness is scoped per academic year, not per school.
            $table->unique(['academic_year_id', 'name']);
            $table->index(['school_id', 'academic_year_id', 'grade_level_id']);
            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_groups');
    }
};
