<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import.students') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:csv,txt,xlsx',
                'mimetypes:text/csv,text/plain,text/x-csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream',
            ],
        ];
    }
}
