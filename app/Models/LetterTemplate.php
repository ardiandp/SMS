<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\LetterTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template surat sekolah yang terversi (PRD-05).
 *
 * Revisi membuat baris baru dengan version + 1 untuk code yang sama; versi
 * lama nonaktif tetapi tidak dihapus agar surat terbit lama tetap punya
 * acuan template. body_template boleh berisi placeholder {{nama_field}}
 * yang diisi saat draf surat dibuat.
 */
class LetterTemplate extends Model
{
    /** @use HasFactory<LetterTemplateFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'body_template',
        'version',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SchoolLetter, $this>
     */
    public function letters(): HasMany
    {
        return $this->hasMany(SchoolLetter::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
