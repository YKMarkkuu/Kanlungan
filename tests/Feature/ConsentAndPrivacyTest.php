<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsentAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function createOtp(string $email): OtpCode
    {
        return OtpCode::create([
            'email' => $email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(OtpCode::TTL_MINUTES),
        ]);
    }

    public function test_registration_without_accepting_terms_is_rejected_and_spends_no_attempt(): void
    {
        $email = 'student1111pam@student.fatima.edu.ph';
        $otp = $this->createOtp($email);

        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '123456',
            'student_id' => '12345678901',
        ])->assertUnprocessable()->assertJsonValidationErrors('terms');

        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '123456',
            'student_id' => '12345678901',
            'terms' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('terms');

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertNotNull($otp->fresh());
        $this->assertSame(0, $otp->fresh()->attempts);
        $this->assertGuest();
    }

    public function test_registration_records_server_side_terms_version_and_time(): void
    {
        $email = 'student2222pam@student.fatima.edu.ph';
        $this->createOtp($email);

        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '123456',
            'student_id' => '12345678902',
            'terms' => true,
            'terms_version' => 'client-made-up',
        ])->assertCreated();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertSame(config('kanlungan.terms_version'), $user->terms_version);
        $this->assertNotNull($user->accepted_terms_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_rules_and_privacy_are_public_and_up_to_date(): void
    {
        $this->get('/rules')->assertOk()
            ->assertSee('How reports work')
            ->assertSee('Likes never give points')
            ->assertDontSee('glows red')
            ->assertDontSee('cm-nav', false);

        $this->get('/privacy')->assertOk()
            ->assertSee('Deleting your account')
            ->assertSee('type DELETE')
            ->assertDontSee('Draft summary')
            ->assertDontSee('cm-nav', false);
    }

    public function test_help_page_remains_login_only(): void
    {
        $this->get('/help')->assertRedirect(route('login'));
    }

    public function test_authenticated_students_get_the_community_layout_on_public_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/rules')->assertOk()->assertSee('cm-nav', false);
        $this->actingAs($user)->get('/privacy')->assertOk()->assertSee('cm-nav', false);
    }

    public function test_register_page_links_to_rules_and_privacy(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee(route('page.rules'))
            ->assertSee(route('page.privacy'));
    }
}
