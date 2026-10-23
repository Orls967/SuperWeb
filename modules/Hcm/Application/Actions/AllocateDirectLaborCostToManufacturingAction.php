<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Actions;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Hcm\Domain\Models\ProductionLaborAllocation;
use Modules\Hcm\Ledger\HcmTransactionTypes;

class AllocateDirectLaborCostToManufacturingAction
{
    protected LedgerService $ledgerService;

    public function __construct(?LedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    public function execute(
        Payroll $payroll,
        string $workOrderRef,
        float $hoursWorked,
        ?int $customHourlyRateIdr = null
    ): ProductionLaborAllocation {
        if ($hoursWorked <= 0.0) {
            throw new InvalidArgumentException('Hours worked must be greater than zero for labor allocation.');
        }

        if (empty($workOrderRef)) {
            throw new InvalidArgumentException('Work order reference is required.');
        }

        $hourlyRate = $customHourlyRateIdr ?? (int) round($payroll->gross_salary_idr / 173.0);
        $allocatedCost = (int) round($hoursWorked * $hourlyRate);

        $allocation = ProductionLaborAllocation::create([
            'payroll_id' => $payroll->id,
            'work_order_ref' => $workOrderRef,
            'hours_worked' => $hoursWorked,
            'allocated_cost_idr' => $allocatedCost,
        ]);

        if ($allocatedCost > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: HcmTransactionTypes::HCM_LABOR_ALLOCATION->value,
                description: "Direct Labor Allocation for WO {$workOrderRef} ({$hoursWorked} hrs)",
                idempotencyKey: "HCM-LABOR-{$allocation->id}",
                entries: Posting::lines()
                    ->debit('mfg:labor_wip:IDR', $allocatedCost)
                    ->credit('expense:hcm:labor_absorbed:IDR', $allocatedCost)
                    ->toEntries('IDR'),
                referenceType: 'PRODUCTION_LABOR',
                referenceId: (string) $allocation->id,
            ));
        }

        return $allocation;
    }
}
