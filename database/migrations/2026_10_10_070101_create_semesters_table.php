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
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
            $table->index(['school_id', 'academic_year_id']);
        });

        DB::statement(
            'ALTER TABLE `semesters` ADD CONSTRAINT `semesters_dates_ordered` CHECK (`starts_on` <= `ends_on`)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
