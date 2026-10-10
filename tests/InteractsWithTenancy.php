<?php

namespace Tests;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithTenancy
{
    /**
     * Create a user with an active membership in the school and a global role.
     * Role assignment uses the teams feature, so the team id is set explicitly.
     */
    protected function makeUserWithRole(School $school, string $roleName): User
    {
        $user = User::factory()->create();

        $role = Role::findOrCreate($roleName, 'web');

        setPermissionsTeamId($school->getKey());
        $user->assignRole($role);
        setPermissionsTeamId(null);

        SchoolUserMembership::factory()->create([
            'school_id' => $school->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return $user;
    }

    /**
     * Act as the user with the tenant context resolved to the school.
     */
    protected function actingInSchool(User $user, School $school): static
    {
        $this->actingAs($user);

        app(CurrentSchool::class)->resolve($user, $school->getKey());

        $this->withSession(['current_school_id' => $school->getKey()]);

        return $this;
    }

    /**
     * Reset the tenant context between requests within the same test.
     */
    protected function clearSchoolContext(): void
    {
        app(CurrentSchool::class)->clear();
    }

    protected function refreshPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}