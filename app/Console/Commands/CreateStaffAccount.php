<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Staff cannot register themselves. An admin creates each account here, then the person signs in at
 * /staff/login with an emailed code (no password is ever stored). DPO accounts get a separate reveal-only page.
 */
class CreateStaffAccount extends Command
{
    protected $signature = 'staff:create {email : Their school email} {role : admin, guidance, moderator or dpo} {--name= : Name shown in the audit log}';

    protected $description = 'Create an admin, guidance, moderator or reveal-only DPO account';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $role = (string) $this->argument('role');

        if (! in_array($role, [...ModerationService::STAFF, 'dpo'], true)) {
            $this->error('Role must be one of: '.implode(', ', [...ModerationService::STAFF, 'dpo']).'.');

            return self::FAILURE;
        }

        if (Validator::make(['email' => $email], ['email' => 'required|email|max:190'])->fails()) {
            $this->error('That does not look like an email address.');

            return self::FAILURE;
        }

        if (str_ends_with($email, '@student.fatima.edu.ph')) {
            $this->error('That is a student address. Staff use their staff email (for example name@fatima.edu.ph).');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('An account with that email already exists. To change its role use: php artisan community:role');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name')) ?: explode('@', $email)[0];

        // role is not mass-assignable on purpose (see docs/PM-HANDOFF.md), so it is set explicitly here.
        $user = new User(['name' => $name, 'email' => $email, 'campus' => 'pampanga', 'verified_at' => now()]);
        $user->forceFill(['role' => $role])->save();

        $this->info("Created {$role} account for {$email}. They can sign in at /staff/login.");

        return self::SUCCESS;
    }
}
