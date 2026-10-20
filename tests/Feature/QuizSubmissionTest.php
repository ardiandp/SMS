<?php

namespace Tests\Feature;

use App\Models\Domain\Academics\Quiz;
use App\Models\Domain\Academics\QuizSubmission;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuizSubmissionTest extends TestCase
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

        // Ensure quiz submission permissions exist
        $permissions = [
            'view.quiz-submissions',
            'submit.quiz-submission',
            'grade.quiz-submission',
            'manage.quiz-submissions',
        ];
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

    public function test_list_quiz_submissions(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $submission = QuizSubmission::factory()->create(['school_id' => $school->id]);

        $response = $this->get(route('quiz-submissions.index'));

        $response->assertStatus(200);
        $response->assertSee($submission->quiz->title);
    }

    public function test_view_quiz_submission_details(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $submission = QuizSubmission::factory()->create(['school_id' => $school->id]);

        $response = $this->get(route('quiz-submissions.show', ['id' => $submission->id]));

        $response->assertStatus(200);
        $response->assertSee($submission->quiz->title);
        $response->assertSee($submission->student->full_name);
    }

    public function test_create_quiz_submission(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $quiz = Quiz::factory()->create(['school_id' => $school->id]);
        $student = Student::factory()->create(['school_id' => $school->id]);

        $response = $this->post(route('quiz-submissions.store'), [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'answers' => ['q1' => 'A', 'q2' => 'B'],
        ]);

        $response->assertRedirect(route('quiz-submissions.index'));
        $this->assertDatabaseHas('7a_quiz_submissions', [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'status' => 'draft',
        ]);
    }

    public function test_update_quiz_submission(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $submission = QuizSubmission::factory()->create([
            'school_id' => $school->id,
            'status' => 'draft',
        ]);

        $response = $this->put(route('quiz-submissions.update', ['id' => $submission->id]), [
            'answers' => ['q1' => 'C', 'q2' => 'D'],
        ]);

        $response->assertRedirect(route('quiz-submissions.index'));
        $this->assertDatabaseHas('7a_quiz_submissions', [
            'id' => $submission->id,
            'status' => 'draft',
        ]);
    }

    public function test_submit_quiz_submission(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $submission = QuizSubmission::factory()->create([
            'school_id' => $school->id,
            'status' => 'draft',
            'answers' => ['q1' => 'A'],
        ]);

        $response = $this->post(route('quiz-submissions.submit', ['id' => $submission->id]));

        $response->assertRedirect(route('quiz-submissions.index'));
        $this->assertDatabaseHas('7a_quiz_submissions', [
            'id' => $submission->id,
            'status' => 'submitted',
        ]);
        $this->assertNotNull(QuizSubmission::find($submission->id)->submitted_at);
    }

    public function test_grade_quiz_submission(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $submission = QuizSubmission::factory()->create([
            'school_id' => $school->id,
            'status' => 'submitted',
        ]);

        $response = $this->post(route('quiz-submissions.grade', ['id' => $submission->id]), [
            'score' => 85,
            'max_score' => 100,
            'feedback' => 'Bagus!',
        ]);

        $response->assertRedirect(route('quiz-submissions.index'));
        $this->assertDatabaseHas('7a_quiz_submissions', [
            'id' => $submission->id,
            'status' => 'graded',
            'score' => 85,
        ]);
    }

    public function test_delete_quiz_submission(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $submission = QuizSubmission::factory()->create([
            'school_id' => $school->id,
            'status' => 'draft',
        ]);

        $response = $this->delete(route('quiz-submissions.destroy', ['id' => $submission->id]));

        $response->assertRedirect(route('quiz-submissions.index'));
        $this->assertDatabaseMissing('7a_quiz_submissions', ['id' => $submission->id]);
    }

    public function test_tenant_isolation(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        QuizSubmission::factory()->create(['school_id' => $otherSchool->id]);

        $response = $this->get(route('quiz-submissions.index'));

        $response->assertStatus(200);
        $response->assertDontSee(
            QuizSubmission::where('school_id', $otherSchool->id)->first()?->quiz->title
        );
    }

    public function test_role_only_access(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        // Staff has no quiz submission permissions (see RoleSeeder), so listing must be denied.
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

        $response = $this->get(route('quiz-submissions.index'));

        $response->assertStatus(403);
    }
}
