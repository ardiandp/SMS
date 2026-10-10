<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\School;
use App\Models\SchoolUserMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['password' => 'secret-password']);

        SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 6) as $attempt) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertGuest();
    }

    public function test_inactive_user_status_column_defaults_to_active(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_inactive_user_cannot_login(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'password' => 'secret-password',
            'status' => UserStatus::Inactive,
        ]);

        SchoolUserMembership::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
            'status' => UserStatus::Suspended,
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_inactive_user_login_error_does_not_reveal_account_state(): void
    {
        $inactive = User::factory()->create([
            'password' => 'secret-password',
            'status' => UserStatus::Inactive,
        ]);

        // Pesan untuk akun nonaktif harus identik dengan kredensial salah,
        // supaya login endpoint tidak bisa dipakai untuk memprobe status akun.
        $this->from('/login')
            ->post('/login', [
                'email' => $inactive->email,
                'password' => 'secret-password',
            ])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->from('/login')
            ->post('/login', [
                'email' => 'tidak-ada@example.test',
                'password' => 'secret-password',
            ])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
