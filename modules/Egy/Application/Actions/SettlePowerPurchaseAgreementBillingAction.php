<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Intercompany\Contracts\IntercompanyPortInterface;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class SettlePowerPurchaseAgreementBillingAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected IntercompanyPortInterface $intercompanyPort
    ) {}

    /**
     * @return array{
     *     status: string,
     *     mirrored_pair_id: string,
     *     total_minor: int,
     *     producer_tx_code: string,
     *     offtaker_tx_code: string
     * }
     */
    public function executeWithIntercompany(string $contractId, int $deliveredKwh, string $idempotencyKey): array
    {
        $ppa = PpaContract::findOrFail($contractId);

        if ($ppa->status !== 'ACTIVE') {
            throw new RuntimeException("PPA contract [{$ppa->contract_number}] is not active.");
        }

        if ($deliveredKwh <= 0) {
            throw new InvalidArgumentException('Delivered kWh must be greater than zero.');
        }

        $rate = (int) $ppa->feed_in_tariff_per_kwh_minor;
        $totalBillMinor = $deliveredKwh * $rate;

        return DB::transaction(function () use ($ppa, $deliveredKwh, $totalBillMinor, $idempotencyKey) {
            $icResult = $this->intercompanyPort->createMirroredPair([
                'selling_entity' => $ppa->producer_entity_id,
                'buying_entity' => $ppa->grid_offtaker_id,
                'amount_idr' => $totalBillMinor,
                'description' => "PPA net settlement for contract {$ppa->contract_number} ({$deliveredKwh} kWh)",
                'idempotency_key' => $idempotencyKey,
                'sales_invoice_ref' => "INV-PPA-{$ppa->contract_number}",
                'purchase_bill_ref' => "BILL-PPA-{$ppa->contract_number}",
                'post_to_ledger' => true,
                'producer_ar_account' => 'ar:ic:producer:egy:IDR',
                'producer_rev_account' => 'revenue:energy:ppa:IDR',
                'offtaker_exp_account' => 'expense:energy:purchase:IDR',
                'offtaker_ap_account' => 'ap:ic:egy:producer:IDR',
            ]);

            $ppa->increment('surplus_kwh_exported', $deliveredKwh);
            $ppa->increment('total_settlement_minor', $totalBillMinor);

            $this->audit(
                action: 'egy_ppa_billing_settled',
                auditable: $ppa,
                newValues: ['delivered_kwh' => $deliveredKwh, 'total_minor' => $totalBillMinor, 'mirrored_pair' => $icResult['transaction_code']],
                impactType: 'financial',
            );

            return [
                'status' => 'SETTLED',
                'mirrored_pair_id' => $icResult['mirrored_pair_id'],
                'total_minor' => $totalBillMinor,
                'producer_tx_code' => $icResult['producer_tx_code'],
                'offtaker_tx_code' => $icResult['offtaker_tx_code'],
            ];
        });
    }

    public function execute(string $contractId, int $deliveredKwh): int
    {
        $idempotencyKey = "egy:ppa:legacy:{$contractId}:".now()->timestamp;
        $result = $this->executeWithIntercompany($contractId, $deliveredKwh, $idempotencyKey);

        return $result['total_minor'];
    }
}
