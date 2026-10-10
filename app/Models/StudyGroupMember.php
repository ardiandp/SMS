<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\PlacementStatus;
use Database\Factories\StudyGroupMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's placement in one study group for a period of time.
 *
 * Rows are never deleted: closing a placement is what frees the student for the
 * next study group. `active_student_id` is a generated column maintained by the
 * database; it is deliberately absent from $fillable.
 *
 * @property string $status
 */
class StudyGroupMember extends Model
{
    /** @use HasFactory<StudyGroupMemberFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'study_group_id',
        'student_id',
        'starts_on',
        'ends_on',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => PlacementStatus::class,
        ];
    }

    /**
     * @return BelongsTo<StudyGroup, $this>
     */
    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isActive(): bool
    {
        return $this->status === PlacementStatus::Active;
    }
}
