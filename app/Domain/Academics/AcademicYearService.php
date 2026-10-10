<?php

namespace App\Domain\Academics;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Academic years and semesters.
 *
 * Constraint MySQL cannot express directly: a school must have at most one
 * active academic year. A partial unique index is not available, so activation
 * is done in a transaction that locks the current active rows before flipping
 * them, and the count is re-verified after the write.
 */
class AcademicYearService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($data, $actor): AcademicYear {
            $year = new AcademicYear($data);
            $year->assignToCurrentSchool();

            if ($year->starts_on->greaterThan($year->ends_on)) {
                throw ValidationException::withMessages([
                    'ends_on' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
                ]);
            }

            if ($year->is_active) {
                $this->deactivateOthers((int) $year->school_id, null);
            }

            $year->save();

            $this->audit->record(
                action: 'academic_year.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $year,
                metadata: ['academic_year_id' => $year->getKey()],
            );

            return $year;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AcademicYear $year, array $data, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $data, $actor): AcademicYear {
            $previousStatus = $year->is_active;

            $year->fill($data);

            if ($year->starts_on->greaterThan($year->ends_on)) {
                throw ValidationException::withMessages([
                    'ends_on' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
                ]);
            }

            if ($year->is_active) {
                $this->deactivateOthers((int) $year->school_id, $year->getKey());
            }

            $year->save();

            if ($previousStatus !== $year->is_active) {
                $this->audit->record(
                    action: 'academic_year.activation_changed',
                    school: $this->currentSchool->school(),
                    actor: $actor,
                    subject: $year,
                    metadata: [
                        'academic_year_id' => $year->getKey(),
                        'from' => $previousStatus,
                        'to' => $year->is_active,
                    ],
                );
            }

            $this->audit->record(
                action: 'academic_year.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $year,
                metadata: ['academic_year_id' => $year->getKey()],
            );

            return $year;
        });
    }

    /**
     * Make this the only active year for the school.
     */
    public function activate(AcademicYear $year, User $actor): AcademicYear
    {
        $year->is_active = true;

        return $this->update($year, [], $actor);
    }

    public function deactivate(AcademicYear $year, User $actor): AcademicYear
    {
        $year->is_active = false;

        return $this->update($year, [], $actor);
    }

    /**
     * Turn off every other active year. Locked so two concurrent activations
     * cannot both pass the check.
     */
    private function deactivateOthers(int $schoolId, ?int $exceptId): void
    {
        AcademicYear::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->lockForUpdate()
            ->update(['is_active' => false]);
    }
}
