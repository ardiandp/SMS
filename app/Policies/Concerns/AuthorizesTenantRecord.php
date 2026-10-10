<?php

namespace App\Policies\Concerns;

/**
 * Shared tenant-ownership checks for policies on school-scoped records.
 *
 * Ownership is always derived from the validated tenant context, never from a
 * value supplied in the request.
 */
trait AuthorizesTenantRecord
{
    protected function inSchool(): bool
    {
        return $this->currentSchool->isResolved();
    }

    protected function owns(object $record): bool
    {
        return $this->inSchool()
            && (int) $record->school_id === (int) $this->currentSchool->school()->getKey();
    }
}
