<?php

namespace App\Console\Commands;

use App\Services\ClassAnnouncementService;
use Illuminate\Console\Command;

class PublishClassAnnouncements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'class-announcements:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish due class notices and expire old notices';

    /**
     * Execute the console command.
     */
    public function handle(ClassAnnouncementService $notices): int
    {
        $result = $notices->processDue();
        $this->components->info($result['published'].' notice(s) published; '.$result['expired'].' notice(s) expired; '.$result['paused'].' notice(s) paused.');

        return self::SUCCESS;
    }
}
