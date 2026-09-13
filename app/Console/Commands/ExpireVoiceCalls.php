<?php

namespace App\Console\Commands;

use App\Services\Chat\VoiceCallService;
use Illuminate\Console\Command;

class ExpireVoiceCalls extends Command
{
    protected $signature = 'calls:expire';

    protected $description = 'Expire unanswered and abandoned audio/video calls';

    public function handle(VoiceCallService $voiceCallService): int
    {
        $expiredCount = $voiceCallService->expireStaleCalls();
        $this->components->info("Expired {$expiredCount} unanswered voice call(s).");

        return self::SUCCESS;
    }
}
