<?php

namespace Tests\Feature;

use App\Domain\Tenancy\MembershipService;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_grant_is_audited(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $school = School::factory()->create();

        app(MembershipService::class)->grant($school, $user, $actor);

        $log = AuditLog::where('action', 'membership.granted')->firstOrFail();

        $this->assertSame($school->id, $log->school_id);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame(SchoolUserMembership::class, $log->subject_type);
        $this->assertNotNull($log->created_at);
    }

    public function test_membership_revoke_is_audited(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $school = School::factory()->create();
        SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        app(MembershipService::class)->revoke($school, $user, $actor);

        $log = AuditLog::where('action', 'membership.revoked')->firstOrFail();

        $this->assertSame($school->id, $log->school_id);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame('inactive', $log->subject()->first()->status->value);
    }

    public function test_tenant_switch_is_audited(): void
    {
        $user = User::factory()->create();
        $school = School::factory()->create();
        SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->post("/schools/{$school->id}/switch");

        $log = AuditLog::where('action', 'tenant.switched')->firstOrFail();

        $this->assertSame($school->id, $log->school_id);
        $this->assertSame($user->id, $log->actor_user_id);
    }

    public function test_audit_log_does_not_store_secrets(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-password']);
        $actor = User::factory()->create();
        $school = School::factory()->create();

        app(MembershipService::class)->grant($school, $user, $actor);

        $log = AuditLog::firstOrFail();

        $this->assertStringNotContainsString('super-secret-password', $log->toJson());
        $this->assertStringNotContainsString('password', strtolower($log->toJson()));
    }

    public function test_audit_log_created_at_is_stable_on_update(): void
    {
        $user = User::factory()->create();
        $school = School::factory()->create();

        app(MembershipService::class)->grant($school, $user);

        $log = AuditLog::firstOrFail();
        $originalCreatedAt = $log->created_at->toDateTimeString();

        // Model mematikan UPDATED_AT sehingga waktu pencatatan tidak berubah.
        $log->update(['metadata_json' => ['note' => 'updated']]);

        $fresh = $log->fresh();
        $this->assertSame($originalCreatedAt, $fresh->created_at->toDateTimeString());
        $this->assertSame('membership.granted', $fresh->action);
    }
}
