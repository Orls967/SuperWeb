<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class SettlePowerPurchaseAgreementBillingAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function execute(string $contractId, int $deliveredKwh): int
    {
        $ppa = PpaContract::findOrFail($contractId);

        if ($ppa->status !== 'ACTIVE') {
            throw new RuntimeException("PPA contract [{$ppa->contract_number}] is not active.");
        }

        $rate = (int) $ppa->feed_in_tariff_per_kwh_minor;
        $totalBillMinor = $deliveredKwh * $rate;

        return DB::transaction(function () use ($ppa, $deliveredKwh, $totalBillMinor) {
            $idempotencyKey = hash('sha256', "PPA-SETTLE:{$ppa->contract_number}:{$deliveredKwh}:{$totalBillMinor}");

            if ($totalBillMinor > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_PPA_NET_METER_SETTLE',
                    description: "PPA net settlement for contract {$ppa->contract_number} ({$deliveredKwh} kWh)",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:power_purchase_expense:IDR', $totalBillMinor)
                        ->credit('egy:producer_payable:IDR', $totalBillMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_PPA_CONTRACT',
                    referenceId: $ppa->contract_number,
                ));
            }

            $ppa->increment('surplus_kwh_exported', $deliveredKwh);
            $ppa->increment('total_settlement_minor', $totalBillMinor);

            $this->audit(
                action: 'egy_ppa_billing_settled',
                auditable: $ppa,
                newValues: ['delivered_kwh' => $deliveredKwh, 'total_minor' => $totalBillMinor],
                impactType: 'financial',
            );

            return $totalBillMinor;
        });
    }
}
