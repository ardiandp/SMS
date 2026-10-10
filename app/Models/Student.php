<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, ScopedBySchool, SoftDeletes;

    protected $fillable = [
        'school_id',
        'nis',
        'nisn',
        'full_name',
        'birth_date',
        'gender',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot(['relationship', 'is_primary', 'can_pickup', 'receives_notifications'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<StudentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    /**
     * @return HasMany<StudentStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class)->orderByDesc('changed_at');
    }
}
