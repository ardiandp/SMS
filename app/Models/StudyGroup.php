<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\StudyGroupStatus;
use Database\Factories\StudyGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class group (rombel). Always belongs to one academic year and one grade
 * level; those two together define the class identity for that year.
 *
 * @property string $name
 */
class StudyGroup extends Model
{
    /** @use HasFactory<StudyGroupFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'grade_level_id',
        'classroom_id',
        'homeroom_employee_id',
        'name',
        'capacity',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => StudyGroupStatus::class,
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'homeroom_employee_id');
    }

    /**
     * Historical membership. A student's current class is derived from the
     * single row with status = active, never from a column on students.
     *
     * @return HasMany<StudyGroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(StudyGroupMember::class);
    }

    /**
     * @return HasMany<TeachingAssignment, $this>
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
