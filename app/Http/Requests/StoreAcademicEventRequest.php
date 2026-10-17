<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AcademicEventCategory;
use App\Models\AcademicEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Membuat/mengubah kegiatan kalender akademik (PRD-05).
 *
 * academic_year_id opsional (null = lintas tahun); bila diisi wajib milik
 * tenant ini. ends_at boleh null (kegiatan satu titik waktu).
 */
class StoreAcademicEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $event = $this->route('academicEvent') ?? $this->route('event');

            return $event instanceof AcademicEvent
                ? $this->user()?->can('update', $event) ?? false
                : $this->user()?->can('create', AcademicEvent::class) ?? false;
        }

        return $this->user()?->can('create', AcademicEvent::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'category' => ['nullable', Rule::enum(AcademicEventCategory::class)],
            'academic_year_id' => [
                'nullable', 'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'audience_json' => ['nullable', 'array'],
        ];
    }
}
