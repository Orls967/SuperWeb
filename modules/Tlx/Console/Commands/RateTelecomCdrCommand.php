<?php

declare(strict_types=1);

namespace Modules\Tlx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tlx\Application\Services\CdrRatingService;
use Modules\Tlx\Domain\Models\IotDevice;

class RateTelecomCdrCommand extends Command
{
    protected $signature = 'tlx:rate-cdr {--period= : Periode penagihan format YYYY-MM}';

    protected $description = 'Rating dan agregasi pemakaian CDR data perangkat IoT Lini 14';

    public function handle(CdrRatingService $ratingService): int
    {
        $period = (string) ($this->option('period') ?: now()->format('Y-m'));
        $this->info("Memulai rating CDR untuk periode {$period}...");

        $entities = IotDevice::distinct()->pluck('owner_entity_id');
        $processed = 0;

        foreach ($entities as $entityId) {
            $summary = $ratingService->calculateMonthlyUsage((string) $entityId, $period, 1000);
            $this->line("Entity {$entityId}: consumed {$summary['total_consumed_bytes']} bytes, excess {$summary['excess_mb']} MB");
            $processed++;
        }

        $this->info("Selesai rating CDR untuk {$processed} entitas.");

        return self::SUCCESS;
    }
}
