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
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->string('relationship', 50)->default('parent');
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_pickup')->nullable();
            $table->boolean('receives_notifications')->default(true);
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
            $table->index(['school_id', 'guardian_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
