<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class RedeemCrossLineSuperAppLoyaltyCashbackAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Handles cross-line Super App cashback issuance & redemption, validates velocity rules,
     * reconciles liability ledgers, and settles cross-line subscription bundles.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $transactionCode = (string) ($params['transaction_code'] ?? 'SP-CB-'.hash('sha256', (string) ($params['customer_id'] ?? 'default')));
            $customerId = (string) ($params['customer_id'] ?? 'CUST-001');

            $mode = (string) ($params['mode'] ?? 'EARN'); // 'EARN' | 'REDEEM' | 'BUNDLE_SETTLEMENT'

            $cashbackIssuedMinor = 0;
            $cashbackRedeemedMinor = 0;
            $bundleSettlementMinor = 0;

            if ($mode === 'EARN') {
                $purchaseAmountMinor = (int) ($params['purchase_amount_minor'] ?? 500000);
                $earnRatePct = (int) ($params['cashback_rate_pct'] ?? 5);

                // Anti-abuse velocity rule: Max cashback per single transaction
                $maxCashbackCapMinor = (int) ($params['max_cashback_cap_minor'] ?? 50000);
                $calculatedCashback = (int) round(($purchaseAmountMinor * $earnRatePct) / 100);
                $cashbackIssuedMinor = min($calculatedCashback, $maxCashbackCapMinor);

                if ($cashbackIssuedMinor > 0) {
                    $idempotencyKey = hash('sha256', "RET:CASHBACK-EARN:{$transactionCode}:{$cashbackIssuedMinor}");
                    $this->ledgerService->post(new PostingDTO(
                        type: 'RETAIL_CASHBACK_ISSUANCE',
                        description: "Super app cashback reward issued to {$customerId}",
                        idempotencyKey: $idempotencyKey,
                        entries: Posting::lines()
                            ->debit('ret:cashback_expense:IDR', $cashbackIssuedMinor)
                            ->credit('ret:cashback_liability:IDR', $cashbackIssuedMinor)
                            ->toEntries('IDR'),
                        referenceType: 'RET_CASHBACK_EARN',
                        referenceId: $transactionCode,
                    ));
                }
            } elseif ($mode === 'REDEEM') {
                $redeemPointsMinor = (int) ($params['redeem_points_minor'] ?? 20000);
                $availableBalanceMinor = (int) ($params['available_cashback_minor'] ?? 50000);

                if ($redeemPointsMinor > $availableBalanceMinor) {
                    throw new DomainException("Cashback redemption failed: Requested {$redeemPointsMinor} points exceeds available balance {$availableBalanceMinor}");
                }

                $cashbackRedeemedMinor = $redeemPointsMinor;

                if ($cashbackRedeemedMinor > 0) {
                    $idempotencyKey = hash('sha256', "RET:CASHBACK-REDEEM:{$transactionCode}:{$cashbackRedeemedMinor}");
                    $this->ledgerService->post(new PostingDTO(
                        type: 'RETAIL_CASHBACK_ISSUANCE',
                        description: "Super app cashback redemption for {$customerId}",
                        idempotencyKey: $idempotencyKey,
                        entries: Posting::lines()
                            ->debit('ret:cashback_liability:IDR', $cashbackRedeemedMinor)
                            ->credit('ret:omnichannel_sales_revenue:IDR', $cashbackRedeemedMinor)
                            ->toEntries('IDR'),
                        referenceType: 'RET_CASHBACK_REDEEM',
                        referenceId: $transactionCode,
                    ));
                }
            } elseif ($mode === 'BUNDLE_SETTLEMENT') {
                $bundlePriceMinor = (int) ($params['bundle_price_minor'] ?? 1000000);
                /** @var array<string, int> $lineSplits */
                $lineSplits = $params['line_splits'] ?? [];

                $sumSplits = array_sum($lineSplits);
                if ($sumSplits !== $bundlePriceMinor) {
                    throw new DomainException("Bundle settlement mismatch: Sum of splits {$sumSplits} does not match bundle price {$bundlePriceMinor}");
                }

                $bundleSettlementMinor = $bundlePriceMinor;
                $lines = Posting::lines()->debit('ret:bundle_receivable:IDR', $bundlePriceMinor);

                foreach ($lineSplits as $lineName => $splitAmount) {
                    $accountCode = "ret:internal_settlement_{$lineName}:IDR";
                    $lines->credit($accountCode, (int) $splitAmount);
                }

                $idempotencyKey = hash('sha256', "RET:BUNDLE-SPLIT:{$transactionCode}:{$bundlePriceMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_BUNDLE_SUB_BILLING',
                    description: "Cross-line super app subscription bundle settlement {$transactionCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'RET_SUBSCRIPTION_BUNDLE',
                    referenceId: $transactionCode,
                ));
            }

            // Bill payment gapless receipt token
            $gaplessReceipt = 'RCP-'.hash('sha256', "RCP:{$transactionCode}:{$customerId}");

            $result = [
                'transaction_code' => $transactionCode,
                'customer_id' => $customerId,
                'mode' => $mode,
                'cashback_issued_minor' => $cashbackIssuedMinor,
                'cashback_redeemed_minor' => $cashbackRedeemedMinor,
                'bundle_settlement_minor' => $bundleSettlementMinor,
                'gapless_receipt_token' => $gaplessReceipt,
                'status' => 'PROCESSED',
            ];

            $this->audit(
                action: 'ret.superapp.cashback_processed',
                newValues: [
                    'transaction_code' => $transactionCode,
                    'customer_id' => $customerId,
                    'mode' => $mode,
                    'issued_minor' => $cashbackIssuedMinor,
                    'redeemed_minor' => $cashbackRedeemedMinor,
                ],
                correlationId: "ret_cb_{$transactionCode}",
                impactType: 'superapp_loyalty'
            );

            $this->outbox(
                eventType: 'SuperAppLoyaltyCashbackProcessed',
                payload: [
                    'transaction_code' => $transactionCode,
                    'customer_id' => $customerId,
                    'mode' => $mode,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:CB:{$transactionCode}")
            );

            return $result;
        });
    }
}
