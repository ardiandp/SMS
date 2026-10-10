<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\ClassroomStatus;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 */
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'name',
        'building',
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
            'status' => ClassroomStatus::class,
        ];
    }

    /**
     * @return HasMany<StudyGroup, $this>
     */
    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class);
    }
}
