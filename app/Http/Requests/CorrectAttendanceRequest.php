<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correcting an attendance record on a finalized session.
 *
 * Requires the dedicated `correct` ability, and a written reason so the audit
 * entry explains why an official number changed.
 */
class CorrectAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('attendanceRecord');

        return $record instanceof AttendanceRecord
            && ($this->user()?->can('correct', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(AttendanceStatus::class),
                Rule::notIn([$this->route('attendanceRecord')?->status?->value]),
            ],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
