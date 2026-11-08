<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Services\HotelMiceAttritionCalculatorService;

final class HotelAuditMiceAttritionCommand extends Command
{
    protected $signature = 'hotel:evaluate-mice-attrition {--contract= : Optional specific contract number}';

    protected $description = 'Audit and calculate room night slippage attrition penalties for completed MICE contracts';

    public function handle(HotelMiceAttritionCalculatorService $attritionService): int
    {
        $this->info('Scanning MICE contracts for room block slippage and attrition penalties...');

        $query = MiceContract::query();
        if ($this->option('contract')) {
            $query->where('contract_number', $this->option('contract'));
        }

        $contracts = $query->limit(50)->get();
        if ($contracts->isEmpty()) {
            $this->warn('No MICE contracts found for attrition audit.');

            return self::SUCCESS;
        }

        $headers = ['Contract', 'Contracted Nights', 'Min Pickup', 'Actual Pickup', 'Shortfall', 'Penalty (IDR)', 'Status'];
        $rows = [];
        $totalPenalties = 0.0;

        foreach ($contracts as $contract) {
            $dto = $attritionService->evaluateAttrition($contract->id);
            $totalPenalties += $dto->attritionPenaltyAmount;

            $rows[] = [
                $dto->contractNumber,
                $dto->contractedRoomNights,
                $dto->minimumPickupCommitmentNights,
                $dto->actualPickedUpRoomNights,
                $dto->shortfallNights,
                number_format($dto->attritionPenaltyAmount, 2),
                $dto->isPenaltyApplicable ? '<fg=red>PENALIZED</>' : '<fg=green>COMPLIANT</>',
            ];
        }

        $this->table($headers, $rows);
        $this->info('Total attrition penalties assessed: IDR '.number_format($totalPenalties, 2));

        return self::SUCCESS;
    }
}
