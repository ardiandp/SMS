<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\School;
use App\Models\SchoolLetter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $letter = $this->route('schoolLetter');

            return $letter instanceof SchoolLetter
                ? $this->user()?->can('update', $letter) ?? false
                : $this->user()?->can('create', SchoolLetter::class) ?? false;
        }

        return $this->user()?->can('create', SchoolLetter::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['nullable', 'exists:letter_templates,id'],
            'letter_number' => ['required', 'string', 'max:100', Rule::unique('school_letters', 'letter_number')->where(function ($q) {
                $q->where('school_id', $this->school()?->getKey());
            })],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['string'],
        ];
    }

    public function school(): ?School
    {
        return app(CurrentSchool::class)->school();
    }
}
