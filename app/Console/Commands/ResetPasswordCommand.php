<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetPasswordCommand extends Command
{
    protected $signature = 'user:reset-password';

    protected $description = 'Reset a user\'s password';

    public function handle(): int
    {
        $email = $this->ask('Email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $password = $this->secret('New password');
        $confirmation = $this->secret('Confirm new password');

        if ($password !== $confirmation) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user->update(['password' => $password]);

        $this->info("Password updated for {$email}.");

        return self::SUCCESS;
    }
}
