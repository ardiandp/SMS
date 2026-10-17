<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pengumuman sekolah (PRD-05). Alur status: draft -> published ->
     * archived. Isi body disanitasi di service sebelum disimpan; kolom ini
     * menyimpan hasil sanitasi, bukan HTML mentah dari browser.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status', 'published_at']);
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('audience_type', 30);
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'announcement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcements');
    }
};
