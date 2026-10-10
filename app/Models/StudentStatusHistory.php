<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\StudentStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStatusHistory extends Model
{
    /** @use HasFactory<StudentStatusHistoryFactory> */
    use HasFactory, ScopedBySchool;

    public const UPDATED_AT = null;

    /**
     * Tabel memakai `changed_at`, bukan `created_at` (lihat migration M01).
     */
    public const CREATED_AT = 'changed_at';

    protected $fillable = [
        'school_id',
        'student_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
