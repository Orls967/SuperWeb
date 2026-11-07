<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Algoritma Bersertifikat H6: FolioSplitRouter.
 * Mempartisi baris pembebanan folio menjadi sub-folio Corporate vs Personal
 * dengan mematuhi pagu kredit korporat dan matriks kategori kontrak.
 */
final class FolioSplitRouter
{
    /**
     * Kategori yang memenuhi syarat untuk dibebankan ke Corporate Direct Billing.
     *
     * @var array<int, string>
     */
    private const CORPORATE_ELIGIBLE_CATEGORIES = [
        'ROOM_CHARGE',
        'HOTEL_TAX',
        'SERVICE_CHARGE',
        'BUSINESS_INTERNET',
        'AIRPORT_TRANSFER',
    ];

    /**
     * @param  array<int, array{line_id: string, category: string, amount_idr: int}>  $lineItems
     */
    public function route(
        string $folioId,
        bool $corporateBillingEnabled,
        int $corporateCreditLimitIdr,
        array $lineItems
    ): FolioSplitCertificate {
        $totalAmount = 0;
        $corpTotal = 0;
        $personalTotal = 0;
        $allocations = [];

        $currentCorpUsage = 0;

        foreach ($lineItems as $item) {
            $amount = max(0, $item['amount_idr']);
            $totalAmount += $amount;
            $cat = strtoupper($item['category']);

            // Evaluasi apakah item masuk Corporate atau Personal
            $isEligibleCorp = $corporateBillingEnabled && in_array($cat, self::CORPORATE_ELIGIBLE_CATEGORIES, true);

            if ($isEligibleCorp) {
                // Cek sisa credit limit korporat
                $availableCredit = max(0, $corporateCreditLimitIdr - $currentCorpUsage);
                if ($amount <= $availableCredit) {
                    $payer = 'CORPORATE';
                    $corpTotal += $amount;
                    $currentCorpUsage += $amount;
                } else {
                    // Jika melebihi batas, bagian yang muat masuk corporate, sisanya personal
                    // Untuk kesederhanaan transaksi atomik: jika tak cukup pagu penuh, alihkan item ke personal
                    $payer = 'PERSONAL';
                    $personalTotal += $amount;
                }
            } else {
                $payer = 'PERSONAL';
                $personalTotal += $amount;
            }

            $allocations[] = [
                'line_id' => $item['line_id'],
                'category' => $cat,
                'amount_idr' => $amount,
                'payer' => $payer,
            ];
        }

        $checksum = hash('sha256', sprintf(
            '%s|%d|%d|%d|%d',
            $folioId,
            $totalAmount,
            $corpTotal,
            $personalTotal,
            count($allocations)
        ));

        return new FolioSplitCertificate(
            folioId: $folioId,
            totalAmountIdr: $totalAmount,
            corporateAmountIdr: $corpTotal,
            personalAmountIdr: $personalTotal,
            allocations: $allocations,
            checksum: $checksum
        );
    }
}
