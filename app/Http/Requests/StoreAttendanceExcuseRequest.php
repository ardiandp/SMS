<?php

namespace App\Http\Requests;

use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceExcuseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceExcuse::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    /**
     * Resolve the record from the route so the service does not have to trust
     * a posted id.
     */
    public function record(): AttendanceRecord
    {
        $record = $this->route('attendanceRecord');

        abort_unless($record instanceof AttendanceRecord, 404);

        return $record;
    }
}
