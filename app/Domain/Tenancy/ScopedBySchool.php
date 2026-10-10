<?php

namespace App\Domain\Tenancy;

use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to models that own a school_id column. Provides tenant-scoped reads
 * (SchoolScope) and a helper to bind the owning school on create.
 *
 * @mixin Model
 */
trait ScopedBySchool
{
    protected static function bootScopedBySchool(): void
    {
        static::addGlobalScope(new SchoolScope);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Fill school_id from the validated tenant context — never from request input.
     */
    public function assignToCurrentSchool(): static
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        if ($schoolId === null) {
            throw new \RuntimeException('No active school context; cannot assign tenant ownership.');
        }

        $this->school_id = $schoolId;

        return $this;
    }
}
