<?php

namespace App\Domain\Assessment;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\GradingPolicyStatus;
use App\Models\AcademicYear;
use App\Models\AssessmentCategory;
use App\Models\GradingPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Versioned grading policies (PRD-04).
 *
 * The calculation never invents rules: weights, grade bands and rounding are
 * configured by the school here, and activating a version archives the
 * previously active one so there is never ambiguity about which rules
 * produced a given final grade.
 */
class GradingPolicyService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, config (weights, grade_bands, rounding), academic_year_id
     */
    public function createDraft(array $data, User $actor): GradingPolicy
    {
        return DB::transaction(function () use ($data, $actor): GradingPolicy {
            $year = AcademicYear::find($data['academic_year_id']);

            if ($year === null || (int) $year->school_id !== (int) $this->currentSchool->school()?->getKey()) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Tahun ajaran tidak valid.',
                ]);
            }

            $config = $this->validatedConfig($data['config']);

            $maxVersion = (int) GradingPolicy::query()
                ->where('name', $data['name'])
                ->max('version');

            $policy = new GradingPolicy([
                'school_id' => $year->school_id,
                'academic_year_id' => $year->getKey(),
                'name' => $data['name'],
                'config_json' => $config,
                'version' => $maxVersion + 1,
                'status' => GradingPolicyStatus::Draft,
            ]);
            $policy->save();

            $this->audit->record(
                action: 'grading_policy.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $policy,
                metadata: [
                    'grading_policy_id' => $policy->getKey(),
                    'name' => $policy->name,
                    'version' => $policy->version,
                ],
            );

            return $policy;
        });
    }

    /**
     * Activate a draft version; the previously active version of the same
     * policy name is archived in the same transaction.
     */
    public function activate(GradingPolicy $policy, User $actor): GradingPolicy
    {
        return DB::transaction(function () use ($policy, $actor): GradingPolicy {
            if ($policy->status === GradingPolicyStatus::Active) {
                return $policy;
            }

            // Validate against current categories: a policy that references a
            // deleted category must not become active silently.
            $this->validatedConfig($policy->config_json);

            GradingPolicy::query()
                ->where('school_id', $policy->school_id)
                ->where('academic_year_id', $policy->academic_year_id)
                ->where('name', $policy->name)
                ->where('status', GradingPolicyStatus::Active)
                ->update(['status' => GradingPolicyStatus::Archived->value]);

            $policy->status = GradingPolicyStatus::Active;
            $policy->save();

            $this->audit->record(
                action: 'grading_policy.activated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $policy,
                metadata: [
                    'grading_policy_id' => $policy->getKey(),
                    'name' => $policy->name,
                    'version' => $policy->version,
                ],
            );

            return $policy;
        });
    }

    /**
     * The single active policy for an academic year, if any.
     */
    public function activeForYear(int $academicYearId): ?GradingPolicy
    {
        return GradingPolicy::query()
            ->where('academic_year_id', $academicYearId)
            ->where('status', GradingPolicyStatus::Active)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Structural validation of the config: weights must reference existing
     * categories of this school and sum to exactly 100, bands must descend,
     * rounding must be a sane number of decimals.
     *
     * @param  array<string, mixed>  $config
     * @return array{weights: array<int, float>, grade_bands: list<array{label: string, min: float}>, rounding: int}
     */
    private function validatedConfig(array $config): array
    {
        $errors = [];

        $weights = $config['weights'] ?? null;
        $bands = $config['grade_bands'] ?? null;
        $rounding = (int) ($config['rounding'] ?? 2);

        if (! is_array($weights) || $weights === []) {
            $errors['config.weights'] = 'Bobot per kategori wajib diisi.';
            $weights = [];
        }

        $categoryIds = AssessmentCategory::query()->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $cleanWeights = [];
        $sum = 0.0;

        foreach ((array) $weights as $categoryId => $weight) {
            if (! in_array((int) $categoryId, $categoryIds, true)) {
                $errors["config.weights.{$categoryId}"] = 'Kategori tidak dikenal di sekolah ini.';

                continue;
            }

            if (! is_numeric($weight) || (float) $weight <= 0) {
                $errors["config.weights.{$categoryId}"] = 'Bobot harus lebih besar dari nol.';

                continue;
            }

            $cleanWeights[(int) $categoryId] = (float) $weight;
            $sum += (float) $weight;
        }

        if ($cleanWeights !== [] && abs($sum - 100.0) > 0.01) {
            $errors['config.weights'] = 'Total bobot harus tepat 100.';
        }

        if (! is_array($bands) || $bands === []) {
            $errors['config.grade_bands'] = 'Daftar batas nilai wajib diisi.';
            $bands = [];
        }

        $cleanBands = [];
        $previousMin = null;

        foreach ((array) $bands as $index => $band) {
            $label = is_array($band) ? (string) ($band['label'] ?? '') : '';
            $min = is_array($band) && isset($band['min']) && is_numeric($band['min']) ? (float) $band['min'] : null;

            if ($label === '' || $min === null || $min < 0 || $min > 100) {
                $errors["config.grade_bands.{$index}"] = 'Setiap batas butuh label dan nilai minimum 0–100.';

                continue;
            }

            if ($previousMin !== null && $min >= $previousMin) {
                $errors["config.grade_bands.{$index}"] = 'Batas nilai harus berurutan menurun.';

                continue;
            }

            $cleanBands[] = ['label' => $label, 'min' => $min];
            $previousMin = $min;
        }

        if ($rounding < 0 || $rounding > 4) {
            $errors['config.rounding'] = 'Pembulatan antara 0 dan 4 desimal.';
            $rounding = 2;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'weights' => $cleanWeights,
            'grade_bands' => $cleanBands,
            'rounding' => $rounding,
        ];
    }
}
