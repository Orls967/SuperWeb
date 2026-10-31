<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Exceptions\IdempotencyConflictException;
use Modules\Ret\Domain\Exceptions\SellerNotEligibleException;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Ret\Domain\Models\RetSellerSettlement;
use Modules\Shared\Application\BaseAction;

class SettleRetailSellerEscrowAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetSellerSettlement
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required for seller settlement.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetSellerSettlement::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->order_id !== $params['order_id']) {
                throw new IdempotencyConflictException($idempotencyKey);
            }

            return $existing;
        }

        $order = RetOrder::findOrFail($params['order_id']);

        if ($order->status !== 'DELIVERED' && $order->status !== 'FULFILLED') {
            throw new DomainException("Order [{$order->order_number}] must be DELIVERED before seller settlement. Current status: {$order->status}");
        }

        $seller = $order->seller ?: RetSeller::findOrFail($order->seller_id);

        if ($seller->kyb_status !== 'VERIFIED') {
            throw new SellerNotEligibleException($seller->seller_name, "KYB status is {$seller->kyb_status}");
        }

        return DB::transaction(function () use ($order, $seller, $idempotencyKey) {
            $merchandiseMinor = $order->total_amount_minor - $order->packaging_deposit_minor;
            $commissionBps = $seller->commission_override_bps ?? 1000; // 10% default
            $commissionAmountMinor = (int) round(($merchandiseMinor * $commissionBps) / 10000);
            $netSellerPayableMinor = $merchandiseMinor - $commissionAmountMinor;

            $this->ensureSellerAccounts($seller->party_id);

            // Post Ledger Settlement: Escrow Debit -> Commission Credit + Seller AP Credit
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_MKTPLACE_SELLER_SETTLE',
                description: "Escrow settlement for order {$order->order_number}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('escrow:payment:IDR', $merchandiseMinor)
                    ->credit('ret:commission_revenue:IDR', $commissionAmountMinor)
                    ->credit("ap:ret:seller:{$seller->party_id}:IDR", $netSellerPayableMinor)
                    ->toEntries('IDR'),
                referenceType: 'RET_SETTLEMENT',
                referenceId: $order->order_number,
            ));

            $settlementCode = 'SETTLE-'.$order->order_number;

            $settlement = RetSellerSettlement::create([
                'id' => (string) Str::uuid(),
                'settlement_code' => $settlementCode,
                'order_id' => $order->id,
                'seller_id' => $seller->id,
                'seller_party_id' => $seller->party_id,
                'gross_order_amount_minor' => $merchandiseMinor,
                'verified_gmv_minor' => $merchandiseMinor,
                'commission_rate_pct' => $commissionBps / 100.0,
                'commission_amount_minor' => $commissionAmountMinor,
                'commission_fee_minor' => $commissionAmountMinor,
                'net_seller_payable_minor' => $netSellerPayableMinor,
                'net_payout_minor' => $netSellerPayableMinor,
                'status' => 'SETTLED',
                'idempotency_key' => $idempotencyKey,
            ]);

            $order->update(['status' => 'SETTLED']);

            $this->audit(
                action: 'ret_seller_settlement_executed',
                auditable: $settlement,
                newValues: [
                    'order_number' => $order->order_number,
                    'net_seller' => $netSellerPayableMinor,
                    'commission' => $commissionAmountMinor,
                ],
                impactType: 'seller_settlement',
            );

            return $settlement;
        });
    }

    protected function ensureSellerAccounts(string $partyId): void
    {
        LedgerAccount::firstOrCreate(
            ['code' => "ap:ret:seller:{$partyId}:IDR"],
            [
                'uuid' => (string) Str::uuid(),
                'name' => "Accounts Payable Seller {$partyId}",
                'asset_code' => 'IDR',
                'kind' => AccountKind::AP->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
