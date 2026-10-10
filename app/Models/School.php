<?php

namespace App\Models;

use App\Enums\SchoolStatus;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'timezone',
        'settings_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SchoolStatus::class,
            'settings_json' => 'array',
        ];
    }

    /**
     * @return HasMany<SchoolUserMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolUserMembership::class);
    }
}
