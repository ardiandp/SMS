<?php

namespace App\Domain\Tenancy;

use App\Enums\MembershipStatus;
use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create or reactivate a membership. Membership change and audit share one transaction.
     */
    public function grant(School $school, User $user, ?User $actor = null): SchoolUserMembership
    {
        return DB::transaction(function () use ($school, $user, $actor): SchoolUserMembership {
            $membership = SchoolUserMembership::updateOrCreate(
                ['school_id' => $school->getKey(), 'user_id' => $user->getKey()],
                ['status' => MembershipStatus::Active->value],
            );

            $this->audit->record(
                action: 'membership.granted',
                school: $school,
                actor: $actor,
                subject: $membership,
                metadata: ['user_id' => $user->getKey()],
            );

            return $membership;
        });
    }

    /**
     * Deactivate a membership; the user immediately loses tenant access.
     */
    public function revoke(School $school, User $user, ?User $actor = null): SchoolUserMembership
    {
        return DB::transaction(function () use ($school, $user, $actor): SchoolUserMembership {
            $membership = SchoolUserMembership::where('school_id', $school->getKey())
                ->where('user_id', $user->getKey())
                ->firstOrFail();

            $membership->update(['status' => MembershipStatus::Inactive->value]);

            $this->audit->record(
                action: 'membership.revoked',
                school: $school,
                actor: $actor,
                subject: $membership,
                metadata: ['user_id' => $user->getKey()],
            );

            return $membership;
        });
    }
}
