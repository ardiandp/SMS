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
        Schema::create('study_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('study_group_id')->constrained('study_groups')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['school_id', 'study_group_id', 'status']);
            $table->index(['school_id', 'student_id', 'starts_on']);
            $table->index(['school_id', 'student_id', 'status']);
        });

        // "One active placement per student" is a rule MySQL/MariaDB cannot express
        // with a normal unique index, because closed rows must stay in the table as
        // history. A generated column that is non-NULL only while the placement is
        // active turns it into a real unique constraint: NULLs never collide, so
        // any number of historical rows coexist while a second active row fails.
        DB::statement(
            'ALTER TABLE `study_group_members`
             ADD COLUMN `active_student_id` BIGINT UNSIGNED
             GENERATED ALWAYS AS (CASE WHEN `status` = \'active\' THEN `student_id` ELSE NULL END) VIRTUAL'
        );

        DB::statement(
            'ALTER TABLE `study_group_members`
             ADD CONSTRAINT `study_group_members_one_active_per_student`
             UNIQUE (`school_id`, `active_student_id`)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_group_members');
    }
};
