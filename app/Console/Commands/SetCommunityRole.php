<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetCommunityRole extends Command
{
    protected $signature = 'community:role {email} {role : student, moderator, guidance, admin or dpo}';

    protected $description = 'Give a user a community or DPO role, or set them back to student';

    public function handle(): int
    {
        $role = $this->argument('role');

        if (! in_array($role, ['student', 'moderator', 'guidance', 'admin', 'dpo'], true)) {
            $this->error('Role must be: student, moderator, guidance, admin or dpo.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email. They must register first.');

            return self::FAILURE;
        }

        User::whereKey($user->id)->update(['role' => $role]);
        $this->info("{$user->email} is now: {$role}");

        return self::SUCCESS;
    }
}
