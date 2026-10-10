<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\GradeLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable class level. `education_level` is free text on purpose so a
 * school can define its own jenjang ladder instead of being limited to
 * SD/SMP/SMA.
 *
 * @property string $code
 * @property int $sequence
 */
class GradeLevel extends Model
{
    /** @use HasFactory<GradeLevelFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'education_level',
        'sequence',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StudyGroup, $this>
     */
    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class);
    }
}
