<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\AssessmentCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category of assessment (e.g. "Tugas", "Ujian Tengah Semester").
 *
 * The slug is what a grading policy references, so it stays stable even if the
 * display name is edited.
 *
 * @property string $name
 * @property string $slug
 */
class AssessmentCategory extends Model
{
    /** @use HasFactory<AssessmentCategoryFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'name',
        'slug',
        'default_weight',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'category_id');
    }
}
