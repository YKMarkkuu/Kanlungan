<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        if (app()->environment('production') && in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
            throw new RuntimeException('Live OTPs would be written to disk. Configure a real mailer, such as SMTP or Resend, in production.');
        }
    }
}
