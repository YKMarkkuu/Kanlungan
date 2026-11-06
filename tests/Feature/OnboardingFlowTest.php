<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_login_redirects_to_onboarding_and_completion_redirects_to_feed(): void
    {
        $email = 'student8765pam@student.fatima.edu.ph';
        $user = User::factory()->create(['email' => $email]);
        $this->createOtp($email);

        $this->postJson('/auth/login', ['email' => $email, 'code' => '123456'])
            ->assertOk()
            ->assertJson([
                'status' => 'authenticated',
                'redirect' => route('onboarding'),
            ]);

        $this->assertFalse($user->fresh()->has_completed_onboarding);
        $this->get('/home')->assertRedirect(route('onboarding'));
        $this->get('/onboarding')
            ->assertOk()
            ->assertSee('Welcome to Kanlungan.')
            ->assertSee('A safe, verified space for OLFU Pampanga.')
            ->assertSee('Posts can be anonymous, protected by a per-thread alias. The system keeps you accountable, but other students never see who you are.')
            ->assertSee('Earn points by marking comments as Helpful on Ask, Help, and Academic posts. Build your reputation.')
            ->assertSee('Safety reports go straight to moderators.')
            ->assertSee('Skip')
            ->assertSee("Let's go!", false);

        $this->post('/onboarding/complete')
            ->assertRedirect(route('feed'));

        $this->assertTrue($user->fresh()->has_completed_onboarding);
        $this->get('/home')->assertRedirect(route('feed'));
        $this->get('/onboarding')->assertRedirect(route('feed'));
    }

    public function test_completed_user_can_restart_onboarding(): void
    {
        $user = User::factory()->create(['has_completed_onboarding' => true]);

        $this->actingAs($user)
            ->postJson('/onboarding/restart')
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($user->fresh()->has_completed_onboarding);
        $this->get('/onboarding')->assertOk();
    }

    public function test_completed_user_login_redirects_directly_to_feed(): void
    {
        $email = 'student9876pam@student.fatima.edu.ph';
        User::factory()->create([
            'email' => $email,
            'has_completed_onboarding' => true,
        ]);
        $this->createOtp($email);

        $this->postJson('/auth/login', ['email' => $email, 'code' => '123456'])
            ->assertOk()
            ->assertJson([
                'status' => 'authenticated',
                'redirect' => route('feed'),
            ]);
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
