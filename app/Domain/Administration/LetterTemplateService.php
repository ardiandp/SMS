<?php

namespace App\Domain\Administration;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\LetterTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Template surat sekolah yang terversi (PRD-05).
 *
 * Membuat template baru untuk code baru; merevisi code yang ada membuat
 * baris baru version + 1 dan menonaktifkan versi lama. Versi lama tidak
 * dihapus: surat terbit lama tetap punya acuan template yang bisa dibaca.
 * Placeholder {{nama_field}} pada body disanitasi lewat sanitizer yang sama
 * dengan pengumuman (daftar putih tag + buang event handler/javascript:).
 */
class LetterTemplateService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly AnnouncementSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): LetterTemplate
    {
        return DB::transaction(function () use ($data, $actor): LetterTemplate {
            $code = trim($data['code']);

            if (LetterTemplate::where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'Kode template sudah dipakai; gunakan revisi untuk mengubahnya.',
                ]);
            }

            $template = new LetterTemplate([
                'code' => $code,
                'name' => $data['name'],
                'body_template' => $this->sanitizer->sanitize($data['body_template']),
                'version' => 1,
                'is_active' => true,
                'created_by' => $actor->getKey(),
            ]);
            $template->assignToCurrentSchool();
            $template->save();

            $this->log($template, $actor, 'letter_template.created');

            return $template;
        });
    }

    /**
     * Revisi: baris baru version + 1, versi lama dinonaktifkan (tidak dihapus).
     *
     * @param  array<string, mixed>  $data
     */
    public function revise(LetterTemplate $template, array $data, User $actor): LetterTemplate
    {
        return DB::transaction(function () use ($template, $data, $actor): LetterTemplate {
            $latest = LetterTemplate::query()
                ->where('code', $template->code)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->firstOrFail();

            $revised = new LetterTemplate([
                'code' => $latest->code,
                'name' => $data['name'] ?? $latest->name,
                'body_template' => array_key_exists('body_template', $data)
                    ? $this->sanitizer->sanitize($data['body_template'])
                    : $latest->body_template,
                'version' => ((int) $latest->version) + 1,
                'is_active' => true,
                'created_by' => $actor->getKey(),
            ]);
            $revised->assignToCurrentSchool();
            $revised->save();

            LetterTemplate::query()
                ->where('code', $latest->code)
                ->where('id', '!=', $revised->getKey())
                ->update(['is_active' => false]);

            $this->log($revised, $actor, 'letter_template.revised');

            return $revised;
        });
    }

    private function log(LetterTemplate $template, User $actor, string $action): void
    {
        $this->audit->record(
            action: $action,
            school: $this->currentSchool->school(),
            actor: $actor,
            subject: $template,
            metadata: [
                'letter_template_id' => $template->getKey(),
                'code' => $template->code,
                'version' => $template->version,
            ],
        );
    }
}
