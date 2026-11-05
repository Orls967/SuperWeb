<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Illuminate\Console\Command;
use Modules\Syariah\Application\Services\AgriSalamMicrofinanceService;
use Modules\Syariah\Domain\Models\SybAgriMilestone;

class AgriNdviMilestoneCheckCommand extends Command
{
    protected $signature = 'syb:agri-ndvi-check';

    protected $description = 'Memeriksa indeks NDVI satelit dan memicu pencairan bertahap pembiayaan salam tani';

    public function handle(AgriSalamMicrofinanceService $agriService): int
    {
        $pendingMilestones = SybAgriMilestone::where('status', 'PENDING')->get();
        $disbursedCount = 0;

        foreach ($pendingMilestones as $milestone) {
            try {
                $agriService->evaluateAndDisburseMilestone($milestone->milestone_code);
                $disbursedCount++;
            } catch (\Throwable $e) {
                // Tanaman belum memenuhi syarat NDVI, lewati
                $this->line("Milestone {$milestone->milestone_code} belum eligible: {$e->getMessage()}");
            }
        }

        $this->info("Pemeriksaan NDVI selesai. {$disbursedCount} milestone salam dicairkan.");

        return self::SUCCESS;
    }
}
