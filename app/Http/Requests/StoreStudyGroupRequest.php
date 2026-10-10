<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\StudyGroupStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\StudyGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Study group create/update.
 *
 * Every foreign key is scoped to the active tenant at the request layer, so a
 * crafted payload referencing another school's academic year, grade level,
 * classroom or teacher fails validation before it reaches the service.
 */
class StoreStudyGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('study_group');

        return $group instanceof StudyGroup
            ? ($this->user()?->can('update', $group) ?? false)
            : ($this->user()?->can('create', StudyGroup::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();
        $groupId = $this->route('study_group');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Unique per academic year, not per school.
                Rule::unique('study_groups', 'name')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->ignore($groupId),
            ],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'grade_level_id' => [
                'required',
                'integer',
                Rule::exists('grade_levels', 'id')->where('school_id', $schoolId),
            ],
            'classroom_id' => [
                'nullable',
                'integer',
                Rule::exists('classrooms', 'id')->where('school_id', $schoolId),
            ],
            'homeroom_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('school_id', $schoolId),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::enum(StudyGroupStatus::class)],
        ];
    }

    public function academicYearId(): int
    {
        return (int) $this->integer('academic_year_id');
    }

    public function gradeLevelId(): int
    {
        return (int) $this->integer('grade_level_id');
    }

    public function classroomId(): ?int
    {
        return $this->filled('classroom_id') ? (int) $this->integer('classroom_id') : null;
    }

    public function homeroomEmployeeId(): ?int
    {
        return $this->filled('homeroom_employee_id') ? (int) $this->integer('homeroom_employee_id') : null;
    }

    public function relatedGradeLevel(): ?GradeLevel
    {
        return $this->gradeLevelId() === 0 ? null : GradeLevel::find($this->gradeLevelId());
    }

    public function relatedClassroom(): ?Classroom
    {
        return $this->classroomId() === null ? null : Classroom::find($this->classroomId());
    }

    public function relatedEmployee(): ?Employee
    {
        return $this->homeroomEmployeeId() === null ? null : Employee::find($this->homeroomEmployeeId());
    }

    public function relatedAcademicYear(): ?AcademicYear
    {
        return AcademicYear::find($this->academicYearId());
    }
}
