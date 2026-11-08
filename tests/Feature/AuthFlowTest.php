<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_code_accepts_valid_email_rejects_invalid_format_and_is_rate_limited(): void
    {
        Mail::fake();
        $email = 'student1234pam@student.fatima.edu.ph';

        $this->postJson('/auth/send-code', ['email' => $email])
            ->assertOk()
            ->assertExactJson(['status' => 'sent']);

        $this->postJson('/auth/send-code', ['email' => 'not-an-email'])
            ->assertUnprocessable();

        $limitedEmail = 'student5678pam@student.fatima.edu.ph';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/auth/send-code', ['email' => $limitedEmail])
                ->assertOk();
        }

        $this->postJson('/auth/send-code', ['email' => $limitedEmail])
            ->assertStatus(429);
    }

    public function test_send_code_reports_delivery_failure_and_keeps_generated_otp(): void
    {
        $email = 'student8765pam@student.fatima.edu.ph';
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('Mail transport unavailable'));

        Mail::shouldReceive('to')
            ->once()
            ->with($email)
            ->andReturn($mailer);

        Log::shouldReceive('error')
            ->once()
            ->with('Mail failed: Mail transport unavailable');

        $this->postJson('/auth/send-code', ['email' => $email])
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'delivery_failed',
                'message' => 'We could not send your code right now. Please try again shortly.',
            ]);

        $this->assertDatabaseHas('otp_codes', ['email' => $email]);
    }

    public function test_verify_code_returns_ok_or_mismatch_and_counts_wrong_attempts_until_locked_out(): void
    {
        $email = 'student2345pam@student.fatima.edu.ph';
        $otp = $this->createOtp($email);

        $this->postJson('/auth/verify-code', ['email' => $email, 'code' => '123456'])
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $response = $this->postJson('/auth/verify-code', ['email' => $email, 'code' => '654321']);
        $response->assertOk()->assertExactJson(['status' => 'mismatch']);
        $this->assertSame(1, $otp->fresh()->attempts);

        $this->postJson('/auth/verify-code', ['email' => $email, 'code' => '654321'])
            ->assertExactJson(['status' => 'mismatch']);
        $this->postJson('/auth/verify-code', ['email' => $email, 'code' => '654321'])
            ->assertExactJson(['status' => 'mismatch']);
        $this->assertSame(OtpCode::MAX_ATTEMPTS, $otp->fresh()->attempts);

        $this->postJson('/auth/verify-code', ['email' => $email, 'code' => '654321'])
            ->assertOk()
            ->assertExactJson(['status' => 'locked_out']);
    }

    public function test_register_creates_user_logs_them_in_and_reports_duplicate_email_or_student_id(): void
    {
        $email = 'student3456pam@student.fatima.edu.ph';
        $studentId = '12345678901';
        $this->createOtp($email);

        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '123456',
            'student_id' => $studentId,
            'display_name' => 'Test Student',
            'terms' => true,
        ])->assertCreated()->assertExactJson([
            'status' => 'registered',
            'redirect' => route('onboarding'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'student_id' => $studentId,
            'display_name' => 'Test Student',
            'campus' => 'pampanga',
            'has_completed_onboarding' => false,
            'terms_version' => config('kanlungan.terms_version'),
        ]);
        $this->assertNotNull(User::where('email', $email)->value('accepted_terms_at'));
        $this->assertAuthenticatedAs(User::where('email', $email)->firstOrFail());
        $this->assertSame(0, OtpCode::where('email', $email)->count());

        auth()->logout();

        $this->createOtp($email);
        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '123456',
            'student_id' => '10987654321',
            'terms' => true,
        ])->assertUnprocessable()->assertExactJson(['status' => 'email_taken']);
        $this->assertGuest();

        $duplicateStudentEmail = 'student4567pam@student.fatima.edu.ph';
        $this->createOtp($duplicateStudentEmail);
        $this->postJson('/auth/register', [
            'email' => $duplicateStudentEmail,
            'code' => '123456',
            'student_id' => $studentId,
            'terms' => true,
        ])->assertUnprocessable()->assertExactJson(['status' => 'student_id_taken']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => $duplicateStudentEmail]);
    }

    public function test_registering_goes_straight_to_onboarding_without_a_second_login(): void
    {
        $email = 'student6789pam@student.fatima.edu.ph';
        $this->createOtp($email);

        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '123456',
            'student_id' => '34567890123',
            'terms' => true,
        ])->assertCreated()->assertSessionMissing('status');

        $this->get('/onboarding')
            ->assertOk()
            ->assertSee('Welcome to Kanlungan.');

        $this->post('/onboarding/complete')->assertRedirect(route('feed'));
        $this->get('/feed')->assertOk();
    }

    public function test_a_wrong_code_does_not_create_or_log_in_anyone(): void
    {
        $email = 'student7890pam@student.fatima.edu.ph';
        $this->createOtp($email);

        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '654321',
            'student_id' => '45678901234',
            'terms' => true,
        ])->assertOk()->assertExactJson(['status' => 'mismatch']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_login_authenticates_registered_user_and_reports_unregistered_email(): void
    {
        $email = 'student4568pam@student.fatima.edu.ph';
        $user = User::factory()->create([
            'email' => $email,
            'student_id' => '23456789012',
            'verified_at' => now(),
        ]);
        $this->createOtp($email);

        $this->postJson('/auth/login', ['email' => $email, 'code' => '123456'])
            ->assertOk()
            ->assertExactJson([
                'status' => 'authenticated',
                'redirect' => route('onboarding'),
            ]);
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $unregisteredEmail = 'student5679pam@student.fatima.edu.ph';
        $this->createOtp($unregisteredEmail);
        $this->postJson('/auth/login', ['email' => $unregisteredEmail, 'code' => '123456'])
            ->assertOk()
            ->assertExactJson(['status' => 'not_registered']);
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session_and_redirects(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->withSession(['logout-test' => 'present'])
            ->post('/auth/logout');

        $response->assertRedirect('/')->assertSessionMissing('logout-test');
        $this->assertGuest();
    }

    private function createOtp(string $email): OtpCode
    {
        return OtpCode::create([
            'email' => $email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(OtpCode::TTL_MINUTES),
        ]);
    }
}
