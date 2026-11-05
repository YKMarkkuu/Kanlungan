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
            ->assertSee('A safe, verified space for OLFU Pampanga students.')
            ->assertSee('Post anonymously. Your identity is protected by a per-thread alias, but the system keeps you accountable.')
            ->assertSee('Earn points by being helpful on Ask, Help, and Academic posts.')
            ->assertSee("Let's go!", false);

        $this->post('/onboarding/complete')
            ->assertRedirect(route('feed'));

        $this->assertTrue($user->fresh()->has_completed_onboarding);
        $this->get('/home')->assertRedirect(route('feed'));
        $this->get('/onboarding')->assertRedirect(route('feed'));
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
