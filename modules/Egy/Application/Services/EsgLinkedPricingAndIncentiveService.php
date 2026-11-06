<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Application\DTOs\GreenLeaseDiscountDTO;
use Modules\Egy\Domain\Enums\EsgRatingTier;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;

/**
 * Layanan penentuan harga utilitas berbasis kinerja ESG (ESG-linked pricing / Green Lease).
 * Tenant mall/hotel dengan skor ESG tinggi mendapatkan diskon tagihan utilitas (green incentive).
 * Diskon dibukukan sebagai contra-revenue pada ledger secara double-entry.
 */
final class EsgLinkedPricingAndIncentiveService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * Hitung diskon utilitas hijau dan bukukan contra-revenue ke ledger jika ada diskon.
     *
     * @param  string  $tenantId  ID pihak tenant
     * @param  string  $propertyCode  Kode properti gedung/mall
     * @param  int  $esgScoreBps  Skor ESG tenant (0 - 10000 bps)
     * @param  int  $grossUtilityBillIdr  Nilai tagihan kotor utilitas (Rupiah penuh integer)
     * @param  string  $billingPeriod  Periode tagihan misal '2026-11'
     */
    public function calculateAndPostGreenDiscount(
        string $tenantId,
        string $propertyCode,
        int $esgScoreBps,
        int $grossUtilityBillIdr,
        string $billingPeriod
    ): GreenLeaseDiscountDTO {
        if ($esgScoreBps < 0 || $esgScoreBps > 10000) {
            throw new InvalidArgumentException('Skor ESG harus berada dalam rentang 0 s/d 10.000 bps.');
        }
        if ($grossUtilityBillIdr < 0) {
            throw new InvalidArgumentException('Tagihan utilitas kotor tidak boleh bernilai negatif.');
        }

        $tier = EsgRatingTier::fromScore($esgScoreBps);
        $discountBps = $tier->utilityDiscountBps();

        $discountAmountIdr = (int) intdiv($grossUtilityBillIdr * $discountBps, 10000);
        $netPayableUtilityBillIdr = $grossUtilityBillIdr - $discountAmountIdr;

        $ledgerTxId = null;

        // Jika terdapat diskon insentif hijau, lakukan pembukuan contra-revenue
        if ($discountAmountIdr > 0) {
            $contraRevenueAccount = 'egy:utility_sales_revenue:IDR'; // Debet pendapatan utilitas (contra-revenue)
            $customerArAccount = EgyAccounts::customerReceivable($tenantId); // Kredit piutang pelanggan (mengurangi AR)

            $postingLines = Posting::lines()
                ->debit($contraRevenueAccount, $discountAmountIdr)
                ->credit($customerArAccount->code, $discountAmountIdr);

            $idempotencyKey = sprintf('GREEN-LEASE-DISCOUNT:%s:%s:%s', $propertyCode, $tenantId, $billingPeriod);

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: sprintf('Diskon Green Lease Utilitas Tier %s untuk Tenant %s Periode %s', $tier->value, $tenantId, $billingPeriod),
                idempotencyKey: $idempotencyKey,
                entries: $postingLines->toEntries('IDR'),
                referenceType: 'GREEN_LEASE_DISCOUNT',
                referenceId: sprintf('%s-%s', $tenantId, $billingPeriod),
            ));

            $ledgerTxId = $tx->id;
        }

        return new GreenLeaseDiscountDTO(
            tenantId: $tenantId,
            propertyCode: $propertyCode,
            esgScoreBps: $esgScoreBps,
            tier: $tier,
            grossUtilityBillIdr: $grossUtilityBillIdr,
            discountPercentageBps: $discountBps,
            discountAmountIdr: $discountAmountIdr,
            netPayableUtilityBillIdr: $netPayableUtilityBillIdr,
            ledgerTransactionId: $ledgerTxId
        );
    }
}
