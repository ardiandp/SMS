<?php

namespace App\Domain\Administration;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AcademicEvent;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kalender akademik & kegiatan sekolah (PRD-05).
 *
 * Aturan: ends_at tidak boleh sebelum starts_at; academic_year_id opsional
 * (null = lintas tahun, mis. libur nasional) tetapi bila diisi harus satu
 * tenant — dicek via exists rule di FormRequest plus verifikasi ulang di
 * sini agar service aman dipanggil langsung.
 */
class AcademicEventService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): AcademicEvent
    {
        return DB::transaction(function () use ($data, $actor): AcademicEvent {
            $year = $this->resolveYear($data['academic_year_id'] ?? null);
            $this->assertDates($data['starts_at'], $data['ends_at'] ?? null);

            $event = new AcademicEvent([
                'academic_year_id' => $year?->getKey(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'] ?? null,
                'category' => $data['category'] ?? 'academic',
                'audience_json' => $data['audience_json'] ?? null,
                'created_by' => $actor->getKey(),
            ]);
            $event->assignToCurrentSchool();
            $event->save();

            $this->log($event, $actor, 'academic_event.created');

            return $event;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AcademicEvent $event, array $data, User $actor): AcademicEvent
    {
        return DB::transaction(function () use ($event, $data, $actor): AcademicEvent {
            $year = array_key_exists('academic_year_id', $data)
                ? $this->resolveYear($data['academic_year_id'])
                : $event->academicYear;

            $starts = $data['starts_at'] ?? $event->starts_at->format('Y-m-d H:i:s');
            $ends = array_key_exists('ends_at', $data) ? $data['ends_at'] : $event->ends_at?->format('Y-m-d H:i:s');
            $this->assertDates($starts, $ends);

            $event->forceFill([
                'academic_year_id' => $year?->getKey(),
                'title' => $data['title'] ?? $event->title,
                'description' => array_key_exists('description', $data) ? $data['description'] : $event->description,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'category' => $data['category'] ?? $event->category,
                'audience_json' => array_key_exists('audience_json', $data) ? $data['audience_json'] : $event->audience_json,
            ])->save();

            $this->log($event, $actor, 'academic_event.updated');

            return $event->refresh();
        });
    }

    public function delete(AcademicEvent $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $id = $event->getKey();
            $event->delete();

            $this->audit->record(
                action: 'academic_event.deleted',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $event,
                metadata: ['academic_event_id' => $id],
            );
        });
    }

    /**
     * Kegiatan mendatang untuk dasbor, terurut waktu mulai.
     *
     * @return Collection<int, AcademicEvent>
     */
    public function upcoming(int $limit = 8): Collection
    {
        return AcademicEvent::query()
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Kegiatan dalam rentang tanggal (untuk halaman kalender).
     *
     * @return Collection<int, AcademicEvent>
     */
    public function between(string $from, string $to): Collection
    {
        return AcademicEvent::query()
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->get();
    }

    private function resolveYear(mixed $yearId): ?AcademicYear
    {
        if ($yearId === null || $yearId === '') {
            return null;
        }

        // Scoped by SchoolScope: id milik sekolah lain resolve ke null → 404
        // yang aman, bukan kebocoran data.
        $year = AcademicYear::find($yearId);

        if ($year === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Tahun ajaran tidak ditemukan.',
            ]);
        }

        return $year;
    }

    private function assertDates(string $startsAt, ?string $endsAt): void
    {
        if ($endsAt !== null && $endsAt !== '' && $endsAt < $startsAt) {
            throw ValidationException::withMessages([
                'ends_at' => 'Waktu selesai tidak boleh sebelum waktu mulai.',
            ]);
        }
    }

    private function log(AcademicEvent $event, User $actor, string $action): void
    {
        $this->audit->record(
            action: $action,
            school: $this->currentSchool->school(),
            actor: $actor,
            subject: $event,
            metadata: ['academic_event_id' => $event->getKey()],
        );
    }
}
