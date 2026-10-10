<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Marking the register.
 *
 * The `statuses` map is keyed by attendance record id. Each id must belong to
 * the session in the route, so a crafted id from another session cannot be
 * marked through this endpoint.
 */
class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('attendanceSession');

        return $session instanceof AttendanceSession
            && ($this->user()?->can('update', $session) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Reject any record id that is not part of this session.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $session = $this->route('attendanceSession');

            if (! $session instanceof AttendanceSession) {
                return;
            }

            $ids = array_map('intval', array_keys($this->input('statuses', [])));

            $validIds = $session->records()->whereIn('id', $ids)->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach (array_diff($ids, $validIds) as $invalid) {
                $validator->errors()->add("statuses.$invalid", 'Catatan absensi tidak valid untuk sesi ini.');
            }
        });
    }

    /**
     * @return array<int, AttendanceStatus>
     */
    public function statuses(): array
    {
        $statuses = [];

        foreach ((array) $this->input('statuses', []) as $recordId => $status) {
            $statuses[(int) $recordId] = AttendanceStatus::from((string) $status);
        }

        return $statuses;
    }
}
