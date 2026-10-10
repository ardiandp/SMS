<?php

namespace App\Domain\Attendance;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AttendanceStatus;
use App\Enums\ExcuseStatus;
use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sick/permission excuses with an optional evidence file.
 *
 * Evidence lives on the private disk under a UUID path and is only served
 * through a controller plus policy, so a leaked id cannot turn into a public
 * URL (PRD-03 security note).
 */
class ExcuseService
{
    private const ALLOWED_MIME_PREFIXES = ['image/', 'application/pdf'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * Submit an excuse for a sick or permitted record.
     */
    public function submit(
        AttendanceRecord $record,
        string $reason,
        User $actor,
        ?UploadedFile $document = null,
    ): AttendanceExcuse {
        return DB::transaction(function () use ($record, $reason, $actor, $document): AttendanceExcuse {
            if (! in_array($record->status, AttendanceStatus::requireExcuse(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'Alasan hanya dapat diajukan untuk absensi berstatus sakit atau izin.',
                ]);
            }

            $path = $document === null ? null : $this->storeDocument($document, $record);

            try {
                $excuse = AttendanceExcuse::create([
                    'school_id' => $record->school_id,
                    'attendance_record_id' => $record->getKey(),
                    'student_id' => $record->student_id,
                    'reason' => $reason,
                    'document_path' => $path,
                    'mime_type' => $document?->getMimeType(),
                    'size_bytes' => $document?->getSize() ?: null,
                    'status' => ExcuseStatus::Pending,
                ]);
            } catch (QueryException $exception) {
                if (str_contains($exception->getMessage(), 'Duplicate entry')) {
                    throw ValidationException::withMessages([
                        'attendance_record_id' => 'Pengajuan alasan untuk catatan ini sudah ada.',
                    ]);
                }

                throw $exception;
            }

            $this->audit->record(
                action: 'attendance_excuse.submitted',
                school: $this->currentSchool->school(),
                actor: $actor,
                metadata: [
                    'attendance_record_id' => $record->getKey(),
                    'student_id' => $record->student_id,
                    'has_document' => $path !== null,
                ],
            );

            return $excuse;
        });
    }

    /**
     * Approve or reject a pending excuse. The reason text is free-form and may
     * contain personal data, so it is deliberately NOT written to the audit
     * metadata (AGENTS.md rule 11).
     */
    public function review(
        AttendanceExcuse $excuse,
        ExcuseStatus $status,
        User $reviewer,
        ?string $note = null,
    ): AttendanceExcuse {
        if ($status === ExcuseStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Hasil review harus disetujui atau ditolak.',
            ]);
        }

        return DB::transaction(function () use ($excuse, $status, $reviewer, $note): AttendanceExcuse {
            $previous = $excuse->status;

            $excuse->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            $this->audit->record(
                action: 'attendance_excuse.reviewed',
                school: $this->currentSchool->school(),
                actor: $reviewer,
                subject: $excuse,
                metadata: [
                    'attendance_excuse_id' => $excuse->getKey(),
                    'from' => $previous->value,
                    'to' => $status->value,
                ],
            );

            return $excuse;
        });
    }

    /**
     * Absolute path of the evidence file, or null when absent or removed.
     */
    public function documentPath(AttendanceExcuse $excuse): ?string
    {
        if (! $excuse->hasDocument()) {
            return null;
        }

        return Storage::disk('local')->exists($excuse->document_path)
            ? Storage::disk('local')->path($excuse->document_path)
            : null;
    }

    private function storeDocument(UploadedFile $file, AttendanceRecord $record): string
    {
        $mime = (string) $file->getMimeType();

        abort_unless(
            ! str_contains($mime, 'octet-stream')
            && collect(self::ALLOWED_MIME_PREFIXES)->contains(
                fn (string $prefix): bool => str_starts_with($mime, $prefix)
            ),
            422,
            'Tipe file bukti tidak diizinkan.',
        );

        $relative = sprintf(
            'attendance-excuses/%d/%s_%s',
            $record->getKey(),
            Str::uuid()->toString(),
            Str::slug($file->getClientOriginalName()),
        );

        Storage::disk('local')->put($relative, file_get_contents($file->getRealPath()));

        return $relative;
    }
}
