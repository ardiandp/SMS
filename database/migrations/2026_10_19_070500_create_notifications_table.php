<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifikasi in-app (PRD-05). Mengikuti skema baku notifications milik
     * Laravel (uuid, type, morph notifiable, data json, read_at) dengan
     * tambahan school_id agar baris ikut ter-scope SchoolScope seperti
     * tabel tenant lainnya — notifikasi tidak boleh jadi jalur bocor data
     * lintas sekolah.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'notifiable_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
