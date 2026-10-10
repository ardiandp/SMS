<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Guard for cross-tenant foreign keys.
 *
 * A plain `foreignId()->constrained()` only proves the referenced row exists; it
 * does NOT prove the row belongs to the same school. Every multi-tenant relation
 * in the academic structure therefore passes through assertSameSchool() so a
 * study group can never be attached to another school's subject or teacher.
 */
class TenantConsistencyGuard
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @param  list<string>  $attributes  attribute names holding foreign keys
     */
    public function assertSameSchool(string $schoolId, object $model, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $value = $model->{$attribute} ?? null;

            if ($value === null) {
                continue;
            }

            if ((int) $value !== (int) $schoolId) {
                throw new RuntimeException(sprintf(
                    'Cross-tenant reference rejected: %s.%s does not belong to school %s.',
                    class_basename($model),
                    $attribute,
                    $schoolId,
                ));
            }
        }
    }

    /**
     * Assert every given related model belongs to the same school.
     *
     * @param  iterable<object>  $models
     * @param  list<string>  $attributes
     */
    public function assertRelated(iterable $models, string $schoolId, array $attributes): void
    {
        foreach ($models as $model) {
            if ($model !== null) {
                $this->assertSameSchool($schoolId, $model, $attributes);
            }
        }
    }

    /**
     * Verify a semester belongs to the given academic year as well as the school.
     *
     * Throws a ValidationException rather than a RuntimeException: picking a
     * semester from another year is user input error and must come back as a
     * form field error, not a 500.
     */
    public function assertSemesterBelongsToYear(Semester $semester, AcademicYear $year): void
    {
        if ((int) $semester->academic_year_id !== (int) $year->getKey()) {
            throw ValidationException::withMessages([
                'semester_id' => 'Semester yang dipilih bukan bagian dari tahun ajaran ini.',
            ]);
        }

        $this->assertSameSchool($year->school_id, $semester, ['school_id']);
    }

    public function activeSchoolId(): int
    {
        $schoolId = $this->currentSchool->school()?->getKey();

        if ($schoolId === null) {
            throw new RuntimeException('No active school context.');
        }

        return (int) $schoolId;
    }
}
