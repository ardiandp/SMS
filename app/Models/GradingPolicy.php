<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\GradingPolicyStatus;
use Database\Factories\GradingPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A versioned set of calculation rules owned by the school (PRD-04).
 *
 * config_json shape:
 * {
 *   "weights": { "<category_id>": 30.00, ... },   // percentages, sum 100
 *   "grade_bands": [ {"label":"A","min":85}, ... ] // descending
 *   "rounding": 2
 * }
 *
 * The calculation never invents rules: it reads them from the version that was
 * active when it ran, and stores that version on the resulting final grade.
 *
 * @property array<string, mixed> $config_json
 * @property int $version
 * @property GradingPolicyStatus $status
 */
class GradingPolicy extends Model
{
    /** @use HasFactory<GradingPolicyFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'config_json',
        'version',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'version' => 'integer',
            'status' => GradingPolicyStatus::class,
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
