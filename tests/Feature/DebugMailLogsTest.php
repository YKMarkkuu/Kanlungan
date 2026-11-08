<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DebugMailLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_mail_logs_requires_admin_or_moderator_role(): void
    {
        $this->get('/debug-mail-logs')->assertRedirect(route('login'));

        $student = User::factory()->create();
        $this->actingAs($student)->get('/debug-mail-logs')->assertForbidden();
    }

    public function test_debug_mail_logs_returns_recent_mail_errors_with_sensitive_values_redacted(): void
    {
        $originalStoragePath = app()->storagePath();
        $temporaryStoragePath = base_path('storage/framework/testing/debug-mail-logs-'.bin2hex(random_bytes(6)));
        File::makeDirectory($temporaryStoragePath.'/logs', 0755, true);

        try {
            app()->useStoragePath($temporaryStoragePath);
            File::put($temporaryStoragePath.'/logs/laravel.log', implode("\n", [
                '[2026-11-08 10:00:00] production.INFO: unrelated entry',
                '[2026-11-08 10:01:00] production.ERROR: Mail failed: Unauthorized for student8765pam@student.fatima.edu.ph key=xkeysib-secret123 code=987813',
                '[2026-11-08 10:02:00] production.ERROR: Mail failed: Sender not verified',
            ]));

            $admin = User::factory()->create(['role' => 'admin']);

            $this->actingAs($admin)->getJson('/debug-mail-logs')
                ->assertOk()
                ->assertExactJson([
                    'errors' => [
                        '[2026-11-08 10:02:00] production.ERROR: Mail failed: Sender not verified',
                        '[2026-11-08 10:01:00] production.ERROR: Mail failed: Unauthorized for [REDACTED_EMAIL] key=[REDACTED_SECRET] code=[REDACTED_CODE]',
                    ],
                ]);
        } finally {
            app()->useStoragePath($originalStoragePath);
            File::deleteDirectory($temporaryStoragePath);
        }
    }
}
