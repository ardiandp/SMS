<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\ClassSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schedule = $this->route('class_schedule');

        return $schedule instanceof ClassSchedule
            ? ($this->user()?->can('update', $schedule) ?? false)
            : ($this->user()?->can('create', ClassSchedule::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return [
            'teaching_assignment_id' => [
                'required',
                'integer',
                Rule::exists('teaching_assignments', 'id')->where('school_id', $schoolId),
            ],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'room_label' => ['nullable', 'string', 'max:100'],
        ];
    }
}
