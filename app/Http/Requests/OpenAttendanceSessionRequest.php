<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\AttendanceSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceSession::class) ?? false;
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
            'session_date' => [
                'required',
                'date',
                'before_or_equal:today',
                // One session per study group per day.
                Rule::unique('attendance_sessions', 'session_date')
                    ->where('school_id', $schoolId)
                    ->where('study_group_id', $this->integer('study_group_id'))
                    ->where('session_type', 'daily'),
            ],
        ];
    }
}
