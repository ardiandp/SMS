<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Columns beyond the data dictionary: previous_status, corrected_by and
     * corrected_at exist because PRD-03 requires a correction to record the
     * actor, the time and the previous value. Without them a correction could
     * not be audited after the fact.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('status', 20)->default('present');
            $table->string('notes', 255)->nullable();

            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();

            // Correction trail (see class docblock).
            $table->string('previous_status', 20)->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->unsignedSmallInteger('correction_count')->default(0);

            $table->timestamps();

            // The core PRD-03 rule: one record per student per session.
            $table->unique(
                ['attendance_session_id', 'student_id'],
                'attendance_records_unique_student_per_session'
            );

            $table->index(['school_id', 'student_id', 'marked_at']);
            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
