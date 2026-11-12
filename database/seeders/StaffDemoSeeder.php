<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Placeholder staff accounts for local testing ONLY, with a password so you can sign in without email.
 * Run: php artisan db:seed --class=StaffDemoSeeder
 *
 * Refuses to run outside APP_ENV=local, and the password sign-in on /staff/login is also local-only.
 * Never create these accounts on a real server.
 */
class StaffDemoSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['email' => 'mqdavid0738pam@admin.fatima.edu.ph', 'password' => 'admin123', 'role' => 'admin', 'name' => 'Demo Admin'],
        ['email' => 'mplaureta0368pam@admin.fatima.edu.ph', 'password' => 'admin123', 'role' => 'admin', 'name' => 'Demo Admin'],
        ['email' => 'guidance0001pam@admin.fatima.edu.ph', 'password' => 'guidance123', 'role' => 'guidance', 'name' => 'Demo Guidance'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('Demo staff accounts are only for APP_ENV=local. Nothing was created.');

            return;
        }

        foreach (self::ACCOUNTS as $account) {
            $user = User::firstOrNew(['email' => $account['email']]);
            $user->forceFill([
                'name' => $account['name'],
                'campus' => 'pampanga',
                'verified_at' => now(),
                'role' => $account['role'],
                'password' => Hash::make($account['password']),
            ])->save();

            $this->command?->info("{$account['role']}: {$account['email']} / {$account['password']}");
        }
    }
}
