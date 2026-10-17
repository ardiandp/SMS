<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Membuat/mengubah draf pengumuman (PRD-05).
 *
 * audiences wajib diisi minimal satu baris bertipe "all" | "role" |
 * "study_group"; pemeriksaan id tenant (role/study_group milik sekolah ini)
 * dilakukan di service agar pesan error-nya konsisten.
 */
class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $announcement = $this->route('announcement');

            return $announcement instanceof Announcement
                ? $this->user()?->can('update', $announcement) ?? false
                : $this->user()?->can('create', Announcement::class) ?? false;
        }

        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*.audience_type' => ['required', 'string', 'in:all,role,study_group'],
            'audiences.*.audience_id' => ['nullable', 'integer'],
        ];
    }
}
