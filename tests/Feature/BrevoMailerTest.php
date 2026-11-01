<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

class BrevoMailerTest extends TestCase
{
    public function test_brevo_mailer_builds_the_http_api_transport_from_service_config(): void
    {
        config([
            'mail.mailers.brevo.key' => null,
            'services.brevo.key' => 'test-api-key',
        ]);

        $this->assertInstanceOf(
            BrevoApiTransport::class,
            Mail::mailer('brevo')->getSymfonyTransport()
        );
    }
}
