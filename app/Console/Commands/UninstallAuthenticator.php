<?php

namespace App\Console\Commands;

use App\Enums\TwoFactorMethod;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class UninstallAuthenticator extends Command
{
    protected $signature = '2fa:uninstall-authenticator';

    protected $description = 'Remove authenticator packages and migrate affected users to email-based 2FA';

    public function handle(): int
    {
        // Step 1: Migrate confirmed authenticator users to email method
        $confirmedUsers = User::where('two_factor_method', TwoFactorMethod::AUTHENTICATOR->value)
            ->whereNotNull('two_factor_confirmed_at')
            ->get();

        if ($confirmedUsers->isNotEmpty()) {
            $this->info("Migrating {$confirmedUsers->count()} user(s) from authenticator to email method:");

            foreach ($confirmedUsers as $user) {
                $user->update([
                    'two_factor_method' => TwoFactorMethod::EMAIL,
                    'two_factor_secret' => null,
                ]);

                $this->line("  - {$user->email}");
            }

            $this->newLine();
            $this->info('Users migrated to email-based 2FA. They will not be locked out.');
        }

        // Step 2: Clear unconfirmed authenticator setups
        $cleared = User::where('two_factor_method', TwoFactorMethod::AUTHENTICATOR->value)
            ->whereNull('two_factor_confirmed_at')
            ->update([
                'two_factor_method' => null,
                'two_factor_secret' => null,
            ]);

        if ($cleared > 0) {
            $this->info("Cleared {$cleared} unconfirmed authenticator setup(s).");
        }

        // Step 3: Remove packages
        $this->info('Removing authenticator packages...');
        $result = Process::path(base_path())->run('composer remove pragmarx/google2fa chillerlan/php-qrcode');

        if ($result->failed()) {
            $this->error('Failed to remove packages.');
            $this->line($result->errorOutput());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Authenticator packages removed.');
        $this->info('Commit composer.json and composer.lock to persist the removal.');

        return self::SUCCESS;
    }
}
