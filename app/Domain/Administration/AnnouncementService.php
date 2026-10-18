<?php

namespace App\Domain\Administration;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Pengumuman sekolah: draf, terbit, arsip (PRD-05).
 *
 * Body disimpan sebagai hasil sanitasi HTML; alur status
 * draft -> published -> archived; terbit wajib punya audience eksplisit
 * ("all", "role", "study_group").
 */
class AnnouncementService
{
    /**
     * @var list<string>
     */
    private const KNOWN_AUDIENCES = ['all', 'role', 'study_group'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
        private readonly AnnouncementSanitizer $sanitizer,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{audience_type: string, audience_id?: int|null}>  $audiences
     */
    public function createDraft(array $data, array $audiences, User $actor): Announcement
    {
        return DB::transaction(function () use ($data, $audiences, $actor): Announcement {
            $announcement = new Announcement([
                'title' => $data['title'],
                'body' => $this->sanitizer->sanitize($data['body'] ?? ''),
                'status' => AnnouncementStatus::Draft,
                'created_by' => $actor->getKey(),
            ]);
            $announcement->assignToCurrentSchool();
            $announcement->save();

            $this->syncAudiences($announcement, $audiences);
            $this->log($announcement, $actor, 'announcement.created');

            return $announcement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{audience_type: string, audience_id?: int|null}>|null  $audiences
     */
    public function updateDraft(Announcement $announcement, array $data, ?array $audiences, User $actor): Announcement
    {
        return DB::transaction(function () use ($announcement, $data, $audiences, $actor): Announcement {
            $this->assertDraft($announcement);

            $announcement->forceFill([
                'title' => $data['title'] ?? $announcement->title,
                'body' => array_key_exists('body', $data)
                    ? $this->sanitizer->sanitize($data['body'])
                    : $announcement->body,
            ])->save();

            if ($audiences !== null) {
                $this->syncAudiences($announcement, $audiences);
            }

            $this->log($announcement, $actor, 'announcement.updated');

            return $announcement->refresh();
        });
    }

    public function publish(Announcement $announcement, User $actor): Announcement
    {
        return DB::transaction(function () use ($announcement, $actor): Announcement {
            $this->assertDraft($announcement);

            if ($announcement->recipients()->count() === 0) {
                throw ValidationException::withMessages([
                    'audiences' => 'Pengumuman harus memiliki minimal satu audience sebelum diterbitkan.',
                ]);
            }

            $announcement->forceFill([
                'status' => AnnouncementStatus::Published,
                'published_at' => now(),
            ])->save();

            $this->log($announcement, $actor, 'announcement.published');

            // Notifikasi audience mengikuti transaksi: bila publish gagal
            // (rollback), notifikasi ikut hilang — tidak ada pemberitahuan
            // untuk pengumuman yang tidak jadi terbit.
            $this->dispatcher->announcePublished($announcement, $actor);

            return $announcement->refresh();
        });
    }

    public function archive(Announcement $announcement, User $actor): Announcement
    {
        return DB::transaction(function () use ($announcement, $actor): Announcement {
            if ($announcement->status !== AnnouncementStatus::Published) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengumuman yang sudah terbit yang dapat diarsipkan.',
                ]);
            }

            $announcement->forceFill(['status' => AnnouncementStatus::Archived])->save();
            $this->log($announcement, $actor, 'announcement.archived');

            return $announcement->refresh();
        });
    }

    /**
     * @param  list<string>  $roleNames
     * @return Collection<int, Announcement>
     */
    public function visibleTo(array $roleNames, int $limit = 10): Collection
    {
        return Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->whereHas('recipients', function ($query) use ($roleNames): void {
                $query->where('audience_type', 'all')
                    ->orWhere(function ($query) use ($roleNames): void {
                        $query->where('audience_type', 'role')
                            ->whereIn('audience_id', $this->roleIds($roleNames));
                    })
                    ->orWhere('audience_type', 'study_group');
            })
            ->with('recipients')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<array{audience_type: string, audience_id?: int|null}>  $audiences
     */
    private function syncAudiences(Announcement $announcement, array $audiences): void
    {
        $announcement->recipients()->delete();

        foreach ($audiences as $audience) {
            $type = $audience['audience_type'] ?? '';

            if (! in_array($type, self::KNOWN_AUDIENCES, true)) {
                throw ValidationException::withMessages([
                    'audiences' => "Tipe audience '{$type}' tidak dikenal.",
                ]);
            }

            $id = $audience['audience_id'] ?? null;

            if (($type === 'all') !== ($id === null)) {
                throw ValidationException::withMessages([
                    'audiences' => 'Audience "all" tanpa id; tipe lain wajib punya audience_id.',
                ]);
            }

            $recipient = new AnnouncementRecipient([
                'announcement_id' => $announcement->getKey(),
                'audience_type' => $type,
                'audience_id' => $id,
            ]);
            $recipient->assignToCurrentSchool();
            $recipient->save();
        }
    }

    private function assertDraft(Announcement $announcement): void
    {
        if ($announcement->status !== AnnouncementStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya draf yang dapat diubah atau diterbitkan.',
            ]);
        }
    }

    private function log(Announcement $announcement, User $actor, string $action): void
    {
        $this->audit->record(
            action: $action,
            school: $this->currentSchool->school(),
            actor: $actor,
            subject: $announcement,
            metadata: ['announcement_id' => $announcement->getKey()],
        );
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<int>
     */
    private function roleIds(array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }

        return Role::query()
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->all();
    }
}
