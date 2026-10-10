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
     * config_json shape (versioned by the `version` column):
     * {
     *   "weights": { "<category_id>": 30.00, ... },   // percentages, sum 100
     *   "grade_bands": [ {"label":"A","min":85}, ... ] // descending
     *   "rounding": 2
     * }
     */
    public function up(): void
    {
        Schema::create('grading_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name', 100);
            $table->json('config_json');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            // Two versions of the same policy name may coexist, but never two
            // identical versions — a policy must be versioned, not duplicated.
            $table->unique(['school_id', 'name', 'version'], 'grading_policies_unique_version');

            $table->index(['school_id', 'academic_year_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_policies');
    }
};