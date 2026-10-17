<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\AnnouncementStatus;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengumuman sekolah (PRD-05).
 *
 * Kolom body menyimpan hasil sanitasi HTML dari AnnouncementService, bukan
 * HTML mentah dari browser. Status mengikuti alur draft -> published ->
 * archived yang ditegakkan service.
 *
 * @property AnnouncementStatus $status
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'title',
        'body',
        'status',
        'published_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnnouncementStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AnnouncementRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
