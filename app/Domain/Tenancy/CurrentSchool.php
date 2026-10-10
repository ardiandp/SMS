<?php

namespace App\Domain\Tenancy;

use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\User;

/**
 * Holds the validated tenant context for the current request/session.
 * Never populated from an untrusted school_id in the request payload.
 */
class CurrentSchool
{
    private ?School $school = null;

    private ?SchoolUserMembership $membership = null;

    /**
     * Resolve the tenant from a membership that belongs to the authenticated user.
     * Returns false when the membership is missing or inactive.
     */
    public function resolve(User $user, int $schoolId): bool
    {
        $membership = SchoolUserMembership::with('school')
            ->where('user_id', $user->getKey())
            ->where('school_id', $schoolId)
            ->first();

        if ($membership === null || $membership->status->value !== 'active') {
            $this->clear();

            return false;
        }

        $this->membership = $membership;
        $this->school = $membership->school;

        setPermissionsTeamId($this->school->getKey());

        return true;
    }

    public function clear(): void
    {
        $this->school = null;
        $this->membership = null;
        setPermissionsTeamId(null);
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function membership(): ?SchoolUserMembership
    {
        return $this->membership;
    }

    public function isResolved(): bool
    {
        return $this->school !== null;
    }
}
