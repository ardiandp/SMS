<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\AcademicEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kegiatan kalender akademik & sekolah (PRD-05).
 *
 * academic_year_id opsional: kegiatan lintas tahun tidak terikat satu tahun
 * ajaran. audience_json opsional berisi daftar audience eksplisit; null
 * berarti seluruh sekolah.
 *
 * @property string $title
 * @property string $category
 */
class AcademicEvent extends Model
{
    /** @use HasFactory<AcademicEventFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'category',
        'audience_json',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'audience_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
