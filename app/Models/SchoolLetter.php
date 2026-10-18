<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\SchoolLetterStatus;
use Database\Factories\SchoolLetterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surat sekolah (PRD-05).
 *
 * body_snapshot adalah isi final saat diterbitkan: disalin dari template
 * aktif + pengisian placeholder, lalu dibekukan. Revisi template belakangan
 * tidak mengubah surat terbit. Nomor surat unik per tenant.
 *
 * @property SchoolLetterStatus $status
 */
class SchoolLetter extends Model
{
    /** @use HasFactory<SchoolLetterFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'template_id',
        'letter_number',
        'subject',
        'body_snapshot',
        'status',
        'issued_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SchoolLetterStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LetterTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
