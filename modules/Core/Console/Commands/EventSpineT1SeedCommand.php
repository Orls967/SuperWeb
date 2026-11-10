<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\database\seeders\EventSpineT1Seeder;

class EventSpineT1SeedCommand extends Command
{
    protected $signature = 'spine:seed-t1';

    protected $description = 'Seed T1 scale dataset for Event Spine (20,000 outbox messages, 7 SM statuses, DLQ, poison messages)';

    public function handle(): int
    {
        $this->info('Starting T1 dataset generation for Universal Event Spine...');
        $start = microtime(true);

        $seeder = new EventSpineT1Seeder;
        $seeder->run();

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("Event Spine T1 seeding completed in {$elapsed} seconds.");

        return self::SUCCESS;
    }
}
