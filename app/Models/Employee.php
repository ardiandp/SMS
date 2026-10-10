<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use App\Enums\EmploymentStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'employee_number',
        'full_name',
        'employment_status',
        'joined_on',
        'left_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    /**
     * @return HasOne<EmployeeUserLink, $this>
     */
    public function userLink(): HasOne
    {
        return $this->hasOne(EmployeeUserLink::class);
    }

    /**
     * @return HasMany<EmployeeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }
}
