<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Template surat & surat sekolah (PRD-05). Template terversi: revisi
     * membuat baris baru dengan version + 1, versi lama tetap ada agar
     * surat yang sudah terbit tidak kehilangan acuannya. Surat terbit
     * menyimpan body_snapshot — isi final saat diterbitkan — sehingga revisi
     * template belakangan tidak mengubah dokumen resmi. letter_number unik
     * per tenant.
     */
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->text('body_template');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'code', 'version'], 'letter_templates_unique_code_version');
            $table->index(['school_id', 'code', 'is_active']);
        });

        Schema::create('school_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('letter_templates')->nullOnDelete();
            $table->string('letter_number', 100);
            $table->string('subject', 200);
            $table->text('body_snapshot');
            $table->string('status', 20)->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'letter_number'], 'school_letters_unique_number');
            $table->index(['school_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_letters');
        Schema::dropIfExists('letter_templates');
    }
};
