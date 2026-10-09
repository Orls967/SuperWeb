<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Ret\Domain\Models\RetBillPayment;
use Modules\Ret\Domain\Models\RetBundleSubscription;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Modules\Ret\Domain\Models\RetCustomerPrivacyProfile;
use Modules\Ret\Domain\Models\RetSubscriptionBundle;
use RuntimeException;

class SuperAppAndCashbackService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * 138.2 Cross-lini Cashback Economy.
     * Test (a): cashback sum issued <= earned rules, non-negative, and ledger liability balanced.
     */
    public function awardCashback(string $customerId, string $sourceLine, int $txAmountMinor, float $cashbackPct): RetCashbackTransaction
    {
        if ($txAmountMinor < 0 || $cashbackPct < 0) {
            throw new RuntimeException('Amount and cashback percentage must be non-negative.');
        }

        $cashbackEarned = (int) round(($txAmountMinor * $cashbackPct) / 100.0);

        return DB::transaction(function () use ($customerId, $sourceLine, $txAmountMinor, $cashbackPct, $cashbackEarned) {
            $code = 'CB-'.strtoupper(Str::random(8));

            // Balanced ledger accrual of cashback points/liability
            if ($cashbackEarned > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_CASHBACK_ISSUANCE',
                    description: "Cashback earned from {$sourceLine} for {$customerId}",
                    idempotencyKey: 'RET-'.$code,
                    entries: [
                        PostingEntryDTO::forCode('ret:cashback_expense:IDR', 'IDR', $cashbackEarned),
                        PostingEntryDTO::forCode('ret:cashback_liability:IDR', 'IDR', -$cashbackEarned),
                    ],
                    referenceType: 'CASHBACK',
                    referenceId: $code,
                ));
            }

            return RetCashbackTransaction::create([
                'id' => (string) Str::uuid(),
                'cashback_code' => $code,
                'customer_id' => $customerId,
                'source_line' => $sourceLine,
                'transaction_amount_minor' => $txAmountMinor,
                'cashback_pct' => $cashbackPct,
                'cashback_earned_minor' => $cashbackEarned,
                'status' => 'EARNED',
            ]);
        });
    }

    /**
     * 138.3 Gapless Bill Payment Receipts.
     * Test (c): bill payment receipt gapless sequential number.
     */
    public function payBill(array $params): RetBillPayment
    {
        return DB::transaction(function () use ($params) {
            $lastSeq = RetBillPayment::lockForUpdate()->max('receipt_sequence') ?? 0;
            $nextSeq = $lastSeq + 1;
            $receiptNum = sprintf('RCP-BILL-%08d', $nextSeq);

            $billAmount = (int) $params['bill_amount_minor'];
            $adminFee = (int) ($params['admin_fee_minor'] ?? 250000);
            $totalPaid = $billAmount + $adminFee;

            // Balanced ledger posting (receivable + fee revenue + biller payable = 0)
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_BILL_PAYMENT_COLLECTION',
                description: "Bill payment {$receiptNum} for {$params['bill_type']}",
                idempotencyKey: 'RET-'.$receiptNum,
                entries: [
                    PostingEntryDTO::forCode('ret:bill_payment_receivable:IDR', 'IDR', $totalPaid),
                    PostingEntryDTO::forCode('ret:bill_payment_fee_revenue:IDR', 'IDR', -$adminFee),
                    PostingEntryDTO::forCode('ret:biller_payable:IDR', 'IDR', -$billAmount),
                ],
                referenceType: 'BILL_PAYMENT',
                referenceId: $receiptNum,
            ));

            return RetBillPayment::create([
                'id' => (string) Str::uuid(),
                'receipt_number' => $receiptNum,
                'receipt_sequence' => $nextSeq,
                'customer_id' => $params['customer_id'],
                'bill_type' => $params['bill_type'],
                'biller_code' => $params['biller_code'],
                'bill_amount_minor' => $billAmount,
                'admin_fee_minor' => $adminFee,
                'total_paid_minor' => $totalPaid,
                'status' => 'PAID',
            ]);
        });
    }

    /**
     * 138.4 Subscription Bundles & Cross-Lini Settlement.
     * Test (b): bundle settlement sum(lines) == subscription fee.
     */
    public function createBundle(array $params): RetSubscriptionBundle
    {
        $breakdown = $params['line_settlement_breakdown'];
        $sumLines = array_sum($breakdown);

        if ($sumLines !== (int) $params['monthly_price_minor']) {
            throw new RuntimeException("Line settlement breakdown sum ({$sumLines}) does not equal monthly price ({$params['monthly_price_minor']}).");
        }

        return RetSubscriptionBundle::create([
            'id' => (string) Str::uuid(),
            'bundle_code' => $params['bundle_code'] ?? 'BDL-'.strtoupper(Str::random(6)),
            'bundle_name' => $params['bundle_name'],
            'monthly_price_minor' => (int) $params['monthly_price_minor'],
            'line_settlement_breakdown' => $breakdown,
            'status' => 'ACTIVE',
        ]);
    }

    public function billBundleSubscription(string $bundleId, string $customerId, string $period): RetBundleSubscription
    {
        $bundle = RetSubscriptionBundle::findOrFail($bundleId);

        return DB::transaction(function () use ($bundle, $customerId, $period) {
            $subCode = 'SUB-'.strtoupper(Str::random(8));

            // Post internal multi-line settlement
            $entries = [
                PostingEntryDTO::forCode('ret:bundle_receivable:IDR', 'IDR', $bundle->monthly_price_minor),
            ];

            foreach ($bundle->line_settlement_breakdown as $line => $amount) {
                $accountCode = "ret:internal_settlement_{$line}:IDR";
                $entries[] = PostingEntryDTO::forCode($accountCode, 'IDR', -$amount);
            }

            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_BUNDLE_SUBSCRIPTION_BILLING',
                description: "Cross-lini bundle subscription {$subCode} for period {$period}",
                idempotencyKey: 'RET-'.$subCode,
                entries: $entries,
                referenceType: 'BUNDLE_SUBSCRIPTION',
                referenceId: $subCode,
            ));

            return RetBundleSubscription::create([
                'id' => (string) Str::uuid(),
                'subscription_code' => $subCode,
                'bundle_id' => $bundle->id,
                'customer_id' => $customerId,
                'amount_billed_minor' => $bundle->monthly_price_minor,
                'billing_period' => $period,
                'status' => 'SETTLED',
            ]);
        });
    }

    /**
     * 138.5 Behavioral Offer Engine & Privacy Opt-out.
     * Test (d): offer engine respects privacy opt-out.
     */
    public function setPrivacyPreference(string $customerId, bool $optOut): RetCustomerPrivacyProfile
    {
        return RetCustomerPrivacyProfile::updateOrCreate(
            ['customer_id' => $customerId],
            [
                'id' => (string) Str::uuid(),
                'marketing_analytics_opt_out' => $optOut,
                'assigned_segment' => $optOut ? 'OPTED_OUT' : 'CROSS_ECOSYSTEM_SHOPPER',
            ]
        );
    }

    public function generatePersonalizedOffer(string $customerId): ?array
    {
        $profile = RetCustomerPrivacyProfile::where('customer_id', $customerId)->first();

        if ($profile && $profile->marketing_analytics_opt_out) {
            return null; // Opted-out: no personalized tracking or cross-scope offer
        }

        return [
            'customer_id' => $customerId,
            'offer_code' => 'OFFER-RESTO-HOTEL-BUNDLE-15',
            'discount_pct' => 15.0,
            'description' => '15% discount on Hotel stay when dining at ecosystem Resto',
        ];
    }
}
