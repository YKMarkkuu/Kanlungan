<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DebugMailLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_mail_logs_requires_a_configured_matching_token(): void
    {
        config(['services.debug_log_token' => 'test-debug-token']);

        $this->get('/debug-mail-logs')->assertForbidden();
        $this->get('/debug-mail-logs?token=wrong-token')->assertForbidden();
    }

    public function test_debug_mail_logs_is_denied_when_no_token_is_configured(): void
    {
        config(['services.debug_log_token' => null]);

        $this->get('/debug-mail-logs?token=anything')->assertForbidden();
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

            config(['services.debug_log_token' => 'test-debug-token']);

            $this->getJson('/debug-mail-logs?token=test-debug-token')
                ->assertOk()
                ->assertExactJson([
                    'notice' => 'Temporary diagnostic endpoint. Remove it after debugging.',
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
