<?php

namespace App\Domain\Administration;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\SchoolLetterStatus;
use App\Models\LetterTemplate;
use App\Models\SchoolLetter;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Surat sekolah: draf dan terbit (PRD-05).
 *
 * Draf boleh dibuat dari template aktif (placeholder {{field}} diisi dari
 * $variables) atau bebas tanpa template. Saat terbit, isi dibekukan ke
 * body_snapshot sehingga revisi template belakangan tidak mengubah dokumen
 * resmi. Nomor surat unik per tenant; duplikat jadi pesan validasi.
 */
class SchoolLetterService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly AnnouncementSanitizer $sanitizer,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $variables
     */
    public function createDraft(array $data, array $variables, User $actor): SchoolLetter
    {
        return DB::transaction(function () use ($data, $variables, $actor): SchoolLetter {
            $template = $this->resolveTemplate($data['template_id'] ?? null);

            $letter = new SchoolLetter([
                'template_id' => $template?->getKey(),
                'letter_number' => trim($data['letter_number']),
                'subject' => $data['subject'],
                'body_snapshot' => $this->buildBody($template, $data['body'] ?? null, $variables),
                'status' => SchoolLetterStatus::Draft,
                'created_by' => $actor->getKey(),
            ]);
            $letter->assignToCurrentSchool();

            try {
                $letter->save();
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e);
            }

            $this->log($letter, $actor, 'school_letter.created');

            return $letter;
        });
    }

    /**
     * Perbarui draf. Hanya surat dengan status draf yang dapat diubah.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $variables
     */
    public function updateDraft(SchoolLetter $letter, array $data, array $variables, User $actor): SchoolLetter
    {
        return DB::transaction(function () use ($letter, $data, $variables, $actor): SchoolLetter {
            if ($letter->status !== SchoolLetterStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya surat draf yang dapat diubah.',
                ]);
            }

            $template = $this->resolveTemplate($data['template_id'] ?? null);

            $letter->forceFill([
                'template_id' => $template?->getKey(),
                'letter_number' => trim($data['letter_number']),
                'subject' => $data['subject'],
                'body_snapshot' => $this->buildBody($template, $data['body'] ?? null, $variables),
            ])->save();

            try {
                $letter->save();
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e);
            }

            $this->log($letter, $actor, 'school_letter.updated');

            return $letter->refresh();
        });
    }

    /**
     * Terbitkan draf: tetapkan status issued dan catat waktu penerbitan.
     */
    public function issue(SchoolLetter $letter, User $actor): SchoolLetter
    {
        return DB::transaction(function () use ($letter, $actor): SchoolLetter {
            if ($letter->status !== SchoolLetterStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya surat draf yang dapat diterbitkan.',
                ]);
            }

            $letter->forceFill([
                'status' => SchoolLetterStatus::Issued,
                'issued_at' => now(),
            ])->save();

            $this->log($letter, $actor, 'school_letter.issued');

            // Notifikasi pengelola surat mengikuti transaksi: bila penerbitan
            // gagal (rollback), notifikasi ikut hilang.
            $this->dispatcher->letterIssued($letter, $actor);

            return $letter->refresh();
        });
    }

    /**
     * Batalkan surat terbit. Baris tidak dihapus agar nomor tetap terlacak.
     */
    public function cancel(SchoolLetter $letter, User $actor): SchoolLetter
    {
        return DB::transaction(function () use ($letter, $actor): SchoolLetter {
            if ($letter->status !== SchoolLetterStatus::Issued) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya surat terbit yang dapat dibatalkan.',
                ]);
            }

            $letter->forceFill(['status' => SchoolLetterStatus::Cancelled])->save();

            $this->log($letter, $actor, 'school_letter.cancelled');

            return $letter->refresh();
        });
    }

    /**
     * Susun isi surat: template aktif + substitusi placeholder, atau body
     * bebas bila tanpa template. Hasil selalu disanitasi.
     *
     * @param  array<string, string>  $variables
     */
    private function buildBody(?LetterTemplate $template, ?string $freeBody, array $variables): string
    {
        if ($template !== null) {
            $filled = preg_replace_callback(
                '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
                fn (array $m): string => (string) ($variables[$m[1]] ?? ''),
                $template->body_template,
            );

            return $this->sanitizer->sanitize($filled ?? '');
        }

        return $this->sanitizer->sanitize($freeBody ?? '');
    }

    private function resolveTemplate(mixed $templateId): ?LetterTemplate
    {
        if ($templateId === null || $templateId === '') {
            return null;
        }

        // Tenant-scoped: id milik sekolah lain resolve ke null → 404 aman.
        $template = LetterTemplate::find($templateId);

        if ($template === null) {
            throw ValidationException::withMessages([
                'template_id' => 'Template surat tidak ditemukan.',
            ]);
        }

        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'template_id' => 'Template sudah tidak aktif; gunakan versi terbaru.',
            ]);
        }

        return $template;
    }

    private function translateUniqueViolation(QueryException $e): never
    {
        if (str_contains($e->getMessage(), 'school_letters_unique_number')) {
            throw ValidationException::withMessages([
                'letter_number' => 'Nomor surat sudah dipakai di sekolah ini.',
            ]);
        }

        throw $e;
    }

    private function log(SchoolLetter $letter, User $actor, string $action): void
    {
        $this->audit->record(
            action: $action,
            school: $this->currentSchool->school(),
            actor: $actor,
            subject: $letter,
            metadata: [
                'school_letter_id' => $letter->getKey(),
                'letter_number' => $letter->letter_number,
            ],
        );
    }
}
