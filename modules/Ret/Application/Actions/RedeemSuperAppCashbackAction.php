<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Exceptions\InsufficientCashbackBalanceException;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Modules\Shared\Application\BaseAction;

class RedeemSuperAppCashbackAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetCashbackTransaction
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to redeem cashback.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetCashbackTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $customerId = (string) $params['customer_id'];
        $amountMinor = (int) $params['amount_minor'];
        $orderId = isset($params['order_id']) ? (string) $params['order_id'] : null;

        // Check available cashback balance
        $earned = (int) RetCashbackTransaction::where('customer_id', $customerId)
            ->where('type', 'EARNED')
            ->sum('amount_minor');

        $spent = (int) RetCashbackTransaction::where('customer_id', $customerId)
            ->whereIn('type', ['REDEEMED', 'EXPIRED'])
            ->sum('amount_minor');

        $available = max(0, $earned - $spent);

        if ($amountMinor > $available) {
            throw new InsufficientCashbackBalanceException($customerId, $amountMinor, $available);
        }

        return DB::transaction(function () use ($customerId, $amountMinor, $orderId, $idempotencyKey) {
            // Post Ledger: Liability Debit -> Receivable / Offset Credit
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_CASHBACK_REDEMPTION',
                description: "Cashback redeemed for order {$orderId}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ret:cashback_liability:IDR', $amountMinor)
                    ->credit('ret:escrow_receivable:IDR', $amountMinor)
                    ->toEntries('IDR'),
                referenceType: 'RET_CASHBACK_REDEMPTION',
                referenceId: $orderId ?? $idempotencyKey,
            ));

            $cashbackCode = 'CB-RED-'.strtoupper(Str::random(8));

            $tx = RetCashbackTransaction::create([
                'id' => (string) Str::uuid(),
                'cashback_code' => $cashbackCode,
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'reference_id' => $orderId,
                'source_domain' => 'superapp',
                'source_line' => 'CHECKOUT',
                'amount_minor' => $amountMinor,
                'transaction_amount_minor' => $amountMinor,
                'cashback_pct' => 0.0,
                'cashback_earned_minor' => 0,
                'type' => 'REDEEMED',
                'status' => 'REDEEMED',
                'expires_at' => null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_cashback_redeemed',
                auditable: $tx,
                newValues: ['amount' => $amountMinor, 'order_id' => $orderId],
                impactType: 'customer_loyalty',
            );

            return $tx;
        });
    }
}
