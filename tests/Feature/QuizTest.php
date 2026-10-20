<?php

namespace Tests\Feature;

use App\Models\Domain\Academics\Quiz;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    private bool $rolesSeeded = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesOnce();
    }

    protected function seedRolesOnce(): void
    {
        if ($this->rolesSeeded) {
            return;
        }

        (new RoleSeeder)->run();

        // Ensure quiz permissions exist
        $permissions = ['view.quizzes', 'manage.quizzes'];
        foreach ($permissions as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        // Give super-admin all permissions
        $superAdmin = Role::findByName('super-admin', 'web');
        if ($superAdmin) {
            $superAdmin->givePermissionTo(\Spatie\Permission\Models\Permission::all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->rolesSeeded = true;
    }

    protected function makeSuperAdmin(User $user, School $school): void
    {
        $role = Role::findByName('super-admin', 'web');
        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
            'school_id' => $school->id,
        ]);
        \App\Models\SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);
        app(\App\Domain\Tenancy\CurrentSchool::class)->resolve($user, $school->getKey());
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school->id]);
    }

    public function test_list_quizzes(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $quiz = Quiz::factory()->create(['school_id' => $school->id]);

        $response = $this->get(route('quizzes.index'));

        $response->assertStatus(200);
        $response->assertSee($quiz->title);
    }

    public function test_view_quiz_details(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $quiz = Quiz::factory()->create(['school_id' => $school->id]);

        $response = $this->get(route('quizzes.show', ['id' => $quiz->id]));

        $response->assertStatus(200);
        $response->assertSee($quiz->title);
    }

    public function test_create_quiz(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $academicYear = \App\Models\AcademicYear::factory()->create(['school_id' => $school->id]);
        $subject = \App\Models\Subject::factory()->create(['school_id' => $school->id]);
        $studyGroup = \App\Models\StudyGroup::factory()->create(['school_id' => $school->id]);

        $response = $this->post(route('quizzes.store'), [
            'academic_year_id' => $academicYear->id,
            'subject_id' => $subject->id,
            'study_group_id' => $studyGroup->id,
            'title' => 'Mid Semester Quiz',
            'description' => 'Quiz for mid semester exam',
            'time_limit' => 60,
            'passing_score' => 70,
        ]);

        $response->assertRedirect(route('quizzes.index'));
        $this->assertDatabaseHas('7a_quizzes', ['title' => 'Mid Semester Quiz']);
    }

    public function test_update_quiz(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $quiz = Quiz::factory()->create(['school_id' => $school->id]);

        $response = $this->put(route('quizzes.update', ['id' => $quiz->id]), [
            'academic_year_id' => $quiz->academic_year_id,
            'subject_id' => $quiz->subject_id,
            'study_group_id' => $quiz->study_group_id,
            'title' => 'Updated Quiz Title',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect(route('quizzes.index'));
        $this->assertDatabaseHas('7a_quizzes', ['title' => 'Updated Quiz Title']);
    }

    public function test_delete_quiz(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $quiz = Quiz::factory()->create(['school_id' => $school->id]);

        $response = $this->delete(route('quizzes.destroy', ['id' => $quiz->id]));

        $response->assertRedirect(route('quizzes.index'));
        $this->assertDatabaseHas('7a_quizzes', ['id' => $quiz->id, 'is_active' => 0]);
    }

    public function test_tenant_isolation(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        Quiz::factory()->create(['school_id' => $otherSchool->id]);

        $response = $this->get(route('quizzes.index'));

        $response->assertStatus(200);
        $response->assertDontSee(Quiz::where('school_id', $otherSchool->id)->first()?->title);
    }

    public function test_role_only_access(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        // Staff has no quiz permissions (see RoleSeeder), so listing must be denied.
        $role = Role::findByName('staff', 'web');
        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
            'school_id' => $school->id,
        ]);
        \App\Models\SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);
        app(\App\Domain\Tenancy\CurrentSchool::class)->resolve($user, $school->getKey());
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school->id]);

        $response = $this->get(route('quizzes.index'));

        $response->assertStatus(403);
    }
}
