<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Overlap between two schedules (same teacher or same room, same day,
     * overlapping time) cannot be expressed as a single index — it needs a range
     * comparison. ScheduleService checks it inside a transaction; see its class
     * docblock for the documented limitation.
     */
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            // restrict, not cascade: a schedule is part of academic history and
            // must be removed deliberately, never as a side effect of a cascade.
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('room_label', 100)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'day_of_week']);
            $table->index(['teaching_assignment_id']);
            $table->index(['school_id', 'room_label', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
