<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * School decision (PRD-03): attendance is taken once per study group per
     * day, so a student has exactly one attendance record per day and the
     * record is never duplicated across subject slots.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('study_group_id')->constrained('study_groups')->restrictOnDelete();
            $table->date('session_date');
            $table->string('session_type', 20)->default('daily');
            $table->foreignId('teaching_assignment_id')->nullable()->constrained('teaching_assignments')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One session per study group per day. `session_type` is part of the
            // key so a future per-period mode cannot silently collide.
            $table->unique(
                ['school_id', 'study_group_id', 'session_date', 'session_type'],
                'attendance_sessions_unique_group_day'
            );

            $table->index(['school_id', 'session_date']);
            $table->index(['school_id', 'study_group_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
