<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Domain\Academics\QuizQuestion;
use App\Models\School;
use App\Models\Subject;
use App\Models\StudyGroup;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuizQuestionTest extends TestCase
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
        $permissions = ['view.quiz-questions', 'manage.quiz-questions'];
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

    public function test_list_quiz_questions(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $question = QuizQuestion::factory()->create(['school_id' => $school->id]);

        $response = $this->get(route('quiz-questions.index'));

        $response->assertStatus(200);
        $response->assertSee($question->question_text);
    }

    public function test_view_quiz_question_details(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $question = QuizQuestion::factory()->create(['school_id' => $school->id]);

        $response = $this->get(route('quiz-questions.show', ['id' => $question->id, 'school_id' => $question->school_id]));

        $response->assertStatus(200);
        $response->assertSee($question->question_text);
    }

    public function test_create_quiz_question(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $response = $this->post(route('quiz-questions.store'), [
            'school_id' => $school->id,
            'academic_year_id' => AcademicYear::factory()->create(['school_id' => $school->id])->id,
            'subject_id' => Subject::factory()->create(['school_id' => $school->id])->id,
            'study_group_id' => StudyGroup::factory()->create(['school_id' => $school->id])->id,
            'question_text' => 'This is a test question',
            'choices' => ['A' => 'Choice 1', 'B' => 'Choice 2'],
            'correct_choice_index' => 0,
        ]);

        $response->assertRedirect(route('quiz-questions.index'));
        $this->assertDatabaseHas('7a_quiz_questions', ['question_text' => 'This is a test question']);
    }

    public function test_update_quiz_question(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $question = QuizQuestion::factory()->create(['school_id' => $school->id]);

        $response = $this->put(route('quiz-questions.update', ['id' => $question->id]), [
            'school_id' => $school->id,
            'academic_year_id' => $question->academic_year_id,
            'subject_id' => $question->subject_id,
            'study_group_id' => $question->study_group_id,
            'question_text' => 'Updated question text',
            'choices' => ['A' => 'Choice 1', 'B' => 'Choice 2'],
            'correct_choice_index' => 0,
        ]);

        $response->assertRedirect(route('quiz-questions.index'));
        $this->assertDatabaseHas('7a_quiz_questions', ['question_text' => 'Updated question text']);
    }

    public function test_delete_quiz_question(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        $question = QuizQuestion::factory()->create(['school_id' => $school->id]);

        $response = $this->delete(route('quiz-questions.destroy', ['id' => $question->id, 'school_id' => $school->id]));

        $response->assertRedirect(route('quiz-questions.index'));
        // Delete is a soft-deactivate (is_active=false), the row stays for history.
        $this->assertDatabaseHas('7a_quiz_questions', ['id' => $question->id, 'is_active' => 0]);
    }

    public function test_tenant_isolation(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $user = User::factory()->create();
        $this->makeSuperAdmin($user, $school);

        QuizQuestion::factory()->create(['school_id' => $otherSchool->id]);

        $response = $this->get(route('quiz-questions.index'));

        $response->assertStatus(200);
        $response->assertDontSee(QuizQuestion::where('school_id', $otherSchool->id)->first()?->question_text);
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

        $response = $this->get(route('quiz-questions.index'));

        $response->assertStatus(403);
    }
}
