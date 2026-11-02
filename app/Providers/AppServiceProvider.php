<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo', function (array $config) {
            $apiKey = $config['key'] ?? config('services.brevo.key');

            if (app()->environment('production') &&
                config('mail.default') === 'brevo' &&
                (! is_string($apiKey) || trim($apiKey) === '')) {
                throw new RuntimeException('Set BREVO_API_KEY to use the Brevo mailer.');
            }

            return new BrevoApiTransport($apiKey);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        if (app()->environment('production') && in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
            throw new RuntimeException('Live OTPs would be written to disk. Configure a real mailer, such as SMTP or Resend, in production.');
        }
    }
}
