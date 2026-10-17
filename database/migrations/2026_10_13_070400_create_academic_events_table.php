<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kalender akademik & kegiatan sekolah (PRD-05). academic_year_id
     * opsional: kegiatan lintas tahun (mis. libur nasional) tidak terikat
     * satu tahun ajaran. audience_json opsional berisi daftar audience
     * eksplisit (mis. ["all"], ["teacher"], ["7A"]); null berarti seluruh
     * sekolah.
     */
    public function up(): void
    {
        Schema::create('academic_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('category', 30)->default('academic');
            $table->json('audience_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'starts_at']);
            $table->index(['school_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_events');
    }
};
