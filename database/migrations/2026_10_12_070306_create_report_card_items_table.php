<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * final_grade_snapshot_json holds the numeric score, label and policy
     * version at publication time. The row is never recomputed, so a later
     * policy change cannot rewrite what the school already published.
     */
    public function up(): void
    {
        Schema::create('report_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('report_card_id')->constrained('report_cards')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->json('final_grade_snapshot_json');
            $table->text('teacher_note')->nullable();
            $table->timestamps();

            $table->unique(['report_card_id', 'subject_id'], 'report_card_items_unique_subject');

            $table->index(['school_id', 'report_card_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_card_items');
    }
};