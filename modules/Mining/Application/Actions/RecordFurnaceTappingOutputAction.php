<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\FurnaceRunStatus;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Domain\StateMachines\FurnaceRunStateMachine;
use Modules\Mining\Ledger\MiningFase121LedgerPostingService;

final class RecordFurnaceTappingOutputAction
{
    public function __construct(
        private readonly FurnaceRunStateMachine $stateMachine,
        private readonly MiningFase121LedgerPostingService $ledgerPostingService
    ) {}

    public function execute(
        MiningSmelterFurnaceRun $run,
        float $metalOutputTons,
        float $slagOutputTons,
        float $tappingTemperatureC = 1520.0
    ): MiningSmelterFurnaceRun {
        if ($metalOutputTons <= 0.0) {
            throw new InvalidArgumentException('Produksi logam (metalOutputTons) harus lebih besar dari 0.');
        }

        $run->metal_output_tons = $metalOutputTons;
        $run->slag_output_tons = $slagOutputTons;
        $run->tapping_temperature_c = $tappingTemperatureC;
        $run->tapped_at = now();

        if ($run->statusEnum() === FurnaceRunStatus::DRAFT) {
            $this->stateMachine->transition($run, FurnaceRunStatus::CHARGING);
            $this->stateMachine->transition($run, FurnaceRunStatus::PREHEATING);
            $this->stateMachine->transition($run, FurnaceRunStatus::SMELTING);
        } elseif ($run->statusEnum() === FurnaceRunStatus::CHARGING) {
            $this->stateMachine->transition($run, FurnaceRunStatus::PREHEATING);
            $this->stateMachine->transition($run, FurnaceRunStatus::SMELTING);
        } elseif ($run->statusEnum() === FurnaceRunStatus::PREHEATING) {
            $this->stateMachine->transition($run, FurnaceRunStatus::SMELTING);
        }

        $this->stateMachine->transition($run, FurnaceRunStatus::TAPPING);
        $this->stateMachine->transition($run, FurnaceRunStatus::COMPLETED);

        // Kapitalisasi persediaan produk logam ke buku besar (Rp 210.000.000 / ton NPI)
        $capitalizationIdr = (int) round($metalOutputTons * 210000000);
        $this->ledgerPostingService->postRefinedMetalsCapitalization(
            runCode: $run->run_code,
            productionCostIdr: $capitalizationIdr,
            metalTons: $metalOutputTons,
            description: "Kapitalisasi perolehan {$metalOutputTons} ton logam NPI batch {$run->run_code}"
        );

        return $run;
    }
}
