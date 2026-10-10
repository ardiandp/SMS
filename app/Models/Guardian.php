<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'full_name',
        'phone',
        'email',
        'address',
    ];

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->withPivot(['relationship', 'is_primary', 'can_pickup', 'receives_notifications'])
            ->withTimestamps();
    }
}
