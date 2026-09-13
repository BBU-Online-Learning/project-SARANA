<?php

namespace App\Console\Commands;

use App\Services\AccountManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CreateFirstSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create-first-super-admin
        {--name= : Full name for the first account}
        {--email= : Unique email for the first account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively create the first Super Admin without exposing credentials';

    /**
     * Execute the console command.
     */
    public function handle(AccountManagementService $accounts): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Full name')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email address'))));
        $password = (string) $this->secret('Temporary password (12+ characters, mixed case, number and symbol)');
        $confirmation = (string) $this->secret('Confirm temporary password');

        if (! hash_equals($password, $confirmation)) {
            $this->error('Password confirmation does not match. No account was created.');

            return self::FAILURE;
        }

        try {
            $attributes = Validator::make([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
            ])->validate();

            $user = $accounts->createFirstSuperAdmin($attributes);
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info("Created Super Admin user {$user->id}. Complete two-factor setup and the mandatory password change at first login.");

        return self::SUCCESS;
    }
}
