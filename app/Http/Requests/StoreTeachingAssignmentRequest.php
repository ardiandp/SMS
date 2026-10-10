<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AssignmentStatus;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('teaching_assignment');

        return $assignment instanceof TeachingAssignment
            ? ($this->user()?->can('update', $assignment) ?? false)
            : ($this->user()?->can('create', TeachingAssignment::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return [
            'study_group_id' => [
                'required',
                'integer',
                Rule::exists('study_groups', 'id')->where('school_id', $schoolId),
            ],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('school_id', $schoolId),
            ],
            'semester_id' => [
                'nullable',
                'integer',
                Rule::exists('semesters', 'id')->where('school_id', $schoolId),
            ],
            'status' => ['required', Rule::enum(AssignmentStatus::class)],
        ];
    }

    public function studyGroup(): ?StudyGroup
    {
        return StudyGroup::find($this->integer('study_group_id'));
    }

    public function subject(): ?Subject
    {
        return Subject::find($this->integer('subject_id'));
    }

    public function employee(): ?Employee
    {
        return Employee::find($this->integer('employee_id'));
    }

    public function semester(): ?Semester
    {
        return $this->filled('semester_id') ? Semester::find($this->integer('semester_id')) : null;
    }
}
