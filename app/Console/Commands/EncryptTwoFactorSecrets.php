<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class EncryptTwoFactorSecrets extends Command
{
    protected $signature = 'auth:encrypt-two-factor-secrets {--commit : Encrypt verified legacy records; default is read-only validation}';

    protected $description = 'Safely validate and migrate legacy two-factor secrets without printing secret values';

    public function handle(AccountManagementService $accounts): int
    {
        $count = 0;
        $failed = 0;
        User::withTrashed()->orderBy('id')->chunkById(100, function ($users) use ($accounts, &$count, &$failed): void {
            foreach ($users as $candidate) {
                try {
                    $accounts->synchronized(function () use ($candidate, &$count): void {
                        $user = User::withTrashed()->lockForUpdate()->findOrFail($candidate->id);
                        $plain = $user->getRawOriginal('google2fa_secret');
                        $encrypted = $user->getRawOriginal('two_factor_secret_encrypted');
                        if ($encrypted) {
                            $decoded = Crypt::decryptString($encrypted);
                            if ($plain !== null && ! hash_equals($decoded, $plain)) {
                                throw new \RuntimeException('Conflicting secret representations.');
                            }
                        } elseif ($plain !== null && ! preg_match('/^[A-Z2-7]{16,128}$/D', $plain)) {
                            throw new \RuntimeException('Unrecognized legacy secret format.');
                        }

                        if ($plain !== null) {
                            $count++;
                            if ($this->option('commit')) {
                                $user->google2fa_secret = $plain;
                                if (! hash_equals($plain, Crypt::decryptString($user->getAttributes()['two_factor_secret_encrypted']))) {
                                    throw new \RuntimeException('Encryption verification failed.');
                                }
                                $user->save();
                            }
                        }
                    });
                } catch (\Throwable) {
                    $failed++;
                    $this->error("User {$candidate->id}: migration refused; record unchanged. Review the record and encryption key securely.");
                }
            }
        });

        $this->info(($this->option('commit') ? 'Migrated' : 'Validated').' legacy records: '.$count.'. Failed: '.$failed.'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
