<?php

namespace App\Console\Commands;

use App\Models\ClassMeetingSeries;
use App\Services\ClassMeetingSeriesService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ReplenishClassMeetingSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'class-meetings:replenish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate upcoming occurrences for active recurring class meetings';

    /**
     * Execute the console command.
     */
    public function handle(ClassMeetingSeriesService $seriesService): int
    {
        $created = 0;
        ClassMeetingSeries::query()->where('status', 'active')->with('schoolClass')->chunkById(100, function ($series) use ($seriesService, &$created): void {
            foreach ($series as $item) {
                $created += $seriesService->replenish($item, CarbonImmutable::today($item->timezone)->addDays(60));
            }
        });
        $this->info("Generated {$created} class meeting occurrences.");

        return self::SUCCESS;
    }
}
