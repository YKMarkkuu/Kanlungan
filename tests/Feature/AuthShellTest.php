<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthShellTest extends TestCase
{
    public function test_login_renders_the_welcome_mat_and_preserves_login_script_hooks(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertSee('A <span class="auth-title__accent">safe space</span> for OLFU Pampanga.', false)
            ->assertSee('Verified, anonymous-optional, and built for our campus community.')
            ->assertSee('Sign in or Register')
            ->assertSee('id="auth-open"', false)
            ->assertSee('id="auth-card"', false)
            ->assertSee('kl-logo', false)
            ->assertSee('@student.fatima.edu.ph')
            ->assertSee(route('page.rules'))
            ->assertSee(route('page.privacy'))
            ->assertSee(route('register'))
            ->assertSee(asset('css/auth.css'))
            ->assertSee(asset('js/landing.js'));

        foreach (['login-form', 'email', 'email-hint', 'login-banner', 'code-block', 'send-btn', 'resend-btn', 'change-email-btn'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
    }

    public function test_register_uses_the_auth_shell_and_preserves_its_steps(): void
    {
        $response = $this->get('/register')->assertOk()
            ->assertSee('auth-shell auth-shell--register', false)
            ->assertSee('kl-logo', false)
            ->assertSee(route('login').'#signin', false);

        foreach (['register', 'step-label', 'details-form', 'rules-form', 'create-btn', 'accept'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }

        $response->assertSee('data-step-panel="1"', false)
            ->assertSee('data-step-panel="2"', false)
            ->assertSee('data-step-panel="3"', false);
    }
}
