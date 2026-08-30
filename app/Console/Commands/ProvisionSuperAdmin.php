<?php

namespace App\Console\Commands;

use App\Services\AccountManagementService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ProvisionSuperAdmin extends Command
{
    protected $signature = 'users:provision-super-admin
        {user : Explicit existing user ID}
        {--confirm-email= : Exact email of the selected account}
        {--commit : Apply the promotion; otherwise validate and preview only}';

    protected $description = 'Explicitly provision the first Super Admin without changing credentials or onboarding';

    public function handle(AccountManagementService $accounts): int
    {
        $id = (string) $this->argument('user');
        $email = (string) $this->option('confirm-email');
        if (! ctype_digit($id) || (int) $id < 1 || $email === '') {
            $this->error('Provide a positive user ID and --confirm-email with its exact email.');

            return self::FAILURE;
        }

        try {
            $user = $accounts->provisionFirstSuperAdmin((int) $id, $email, (bool) $this->option('commit'));
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($this->option('commit')
            ? "User {$user->id} is now the first Super Admin. Credentials and onboarding are unchanged."
            : "Validated user {$user->id}. No changes made. Repeat with --commit to provision.");

        return self::SUCCESS;
    }
}
