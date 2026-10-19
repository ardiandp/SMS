<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relasi akun portal ke profil siswa/wali (PRD-06).
     *
     * Pola mengikuti employee_user_links: satu akun menempel ke satu profil
     * per tenant (UNIQUE school+user), tetapi satu profil boleh diakses
     * beberapa akun bila dibutuhkan (tanpa UNIQUE di sisi profil — mis.
     * ayah dan ibu memakai akun masing-masing menuju satu baris guardian).
     * Wali multi-anak tidak perlu baris ganda: anak-anak dibaca lewat
     * student_guardians milik guardian yang ter-link.
     */
    public function up(): void
    {
        Schema::create('student_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'user_id'], 'student_user_links_unique_school_user');
        });

        Schema::create('guardian_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'user_id'], 'guardian_user_links_unique_school_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_user_links');
        Schema::dropIfExists('student_user_links');
    }
};
