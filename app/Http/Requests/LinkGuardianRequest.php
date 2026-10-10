<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            && ($this->user()?->can('update', $student) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return [
            // Scoped to the active tenant so a guardian id from another school
            // can never pass validation (PRD-01: same-tenant validation).
            'guardian_id' => [
                'required',
                'integer',
                Rule::exists('guardians', 'id')->where('school_id', $schoolId),
            ],
            'relationship' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * Resolve the guardian after validation; the tenant constraint above already
     * guarantees it belongs to the active school.
     */
    public function guardian(): Guardian
    {
        /** @var Guardian $guardian */
        $guardian = Guardian::findOrFail($this->integer('guardian_id'));

        return $guardian;
    }
}
