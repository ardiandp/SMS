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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('employee_number', 50);
            $table->string('full_name', 191);
            $table->string('employment_status', 30)->default('permanent');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'employee_number']);
            $table->index(['school_id', 'employment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
