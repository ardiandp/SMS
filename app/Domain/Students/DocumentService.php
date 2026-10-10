<?php

namespace App\Domain\Students;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    private const ALLOWED_MIME_PREFIXES = ['image/', 'application/pdf'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * Store a student document on the private disk (never publicly served).
     */
    public function store(UploadedFile $file, Student $student, string $documentType, User $actor): StudentDocument
    {
        $mime = (string) $file->getMimeType();

        abort_unless(
            str_contains($mime, 'octet-stream') === false
            && collect(self::ALLOWED_MIME_PREFIXES)->contains(fn (string $prefix): bool => str_starts_with($mime, $prefix)),
            422,
            'Tipe file tidak diizinkan.',
        );

        $relativePath = sprintf(
            'students/%d/%s_%s',
            $student->getKey(),
            Str::uuid()->toString(),
            Str::slug($file->getClientOriginalName()),
        );

        // disk 'local' = storage/app/private → tidak terjangkau lewat URL publik.
        Storage::disk('local')->put($relativePath, file_get_contents($file->getRealPath()));

        $document = StudentDocument::create([
            'school_id' => $student->school_id,
            'student_id' => $student->getKey(),
            'document_type' => $documentType,
            'storage_path' => $relativePath,
            'mime_type' => $mime,
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by' => $actor->getKey(),
        ]);

        $this->audit->record(
            action: 'document.uploaded',
            school: $this->currentSchool->school(),
            actor: $actor,
            subject: $document,
            metadata: ['student_id' => $student->getKey(), 'document_type' => $documentType],
        );

        return $document;
    }

    /**
     * Return the absolute path for download, or null when the file is gone.
     */
    public function pathFor(StudentDocument $document): ?string
    {
        return Storage::disk('local')->exists($document->storage_path)
            ? Storage::disk('local')->path($document->storage_path)
            : null;
    }
}
