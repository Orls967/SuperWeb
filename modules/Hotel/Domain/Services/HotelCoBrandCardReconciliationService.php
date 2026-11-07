<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelCoBrandCardReconciliationService
{
    /**
     * @param list<array{
     *     card_transaction_id: string,
     *     bank_code: string,
     *     travel_pass_number: string,
     *     spend_amount_idr: int,
     *     expected_points: int,
     *     bank_settlement_reference: string
     * }> $bankStatements
     * @return array{
     *     bank_code: string,
     *     statement_date: string,
     *     total_transactions: int,
     *     matched_transactions: int,
     *     mismatched_transactions: int,
     *     total_spend_idr: int,
     *     total_rebate_points: int,
     *     is_reconciled: bool,
     *     discrepancies: list<array<string, mixed>>
     * }
     */
    public function reconcileBankStatementFile(string $bankCode, array $bankStatements): array
    {
        $totalSpend = 0;
        $totalPoints = 0;
        $matched = 0;
        $mismatched = 0;
        $discrepancies = [];

        foreach ($bankStatements as $stmt) {
            $totalSpend += $stmt['spend_amount_idr'];
            $totalPoints += $stmt['expected_points'];

            $pass = HtlTravelPass::where('pass_number', $stmt['travel_pass_number'])->first();
            if (! $pass) {
                $mismatched++;
                $discrepancies[] = [
                    'transaction_id' => $stmt['card_transaction_id'],
                    'issue' => "Nomor Travel Pass '{$stmt['travel_pass_number']}' tidak ditemukan dalam database.",
                ];

                continue;
            }

            // Calculation check: 1 point per 100 IDR base spend ratio
            $calculatedPts = (int) round($stmt['spend_amount_idr'] * 0.015 / 100);
            if ($calculatedPts > 0 && abs($calculatedPts - $stmt['expected_points']) > 50) {
                $mismatched++;
                $discrepancies[] = [
                    'transaction_id' => $stmt['card_transaction_id'],
                    'issue' => "Poin yang diklaim bank ({$stmt['expected_points']}) berbeda dari perhitungan internal ({$calculatedPts}).",
                ];
            } else {
                $matched++;
            }
        }

        return [
            'bank_code' => $bankCode,
            'statement_date' => now()->toDateString(),
            'total_transactions' => count($bankStatements),
            'matched_transactions' => $matched,
            'mismatched_transactions' => $mismatched,
            'total_spend_idr' => $totalSpend,
            'total_rebate_points' => $totalPoints,
            'is_reconciled' => $mismatched === 0,
            'discrepancies' => $discrepancies,
        ];
    }
}
