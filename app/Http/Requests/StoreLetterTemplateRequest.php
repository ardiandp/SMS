<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\LetterTemplate;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLetterTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $template = $this->route('letterTemplate');

            return $template instanceof LetterTemplate
                ? $this->user()?->can('update', $template) ?? false
                : $this->user()?->can('create', LetterTemplate::class) ?? false;
        }

        return $this->user()?->can('create', LetterTemplate::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Kode bersifat tetap: wajib saat membuat, diabaikan saat revisi
            // (revisi membuat baris baru version + 1 dengan kode yang sama).
            'code' => [
                Rule::requiredIf($this->isMethod('post')),
                'string',
                'max:50',
                Rule::unique('letter_templates', 'code')
                    ->ignore($this->route('letterTemplate')?->getKey())
                    ->where(function ($q) {
                        $q->where('school_id', $this->school()?->getKey());
                    }),
            ],
            'name' => ['required', 'string', 'max:200'],
            'body_template' => ['required', 'string'],
        ];
    }

    public function school(): ?School
    {
        return app(CurrentSchool::class)->school();
    }
}
