<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class CorporateDirectBillingCreditScorer
{
    /**
     * Menghitung skor kelayakan kredit korporat (Credit Score 0 - 1000) berdasarkan rasio likuiditas, riwayat pembayaran, dan skala usaha.
     *
     * @param array{
     *     paid_invoices_count: int,
     *     total_historical_spend_idr: int,
     *     average_days_to_pay: float,
     *     late_payments_count: int,
     *     disputed_invoices_count: int,
     *     years_in_business: int
     * } $profile
     * @return array{
     *     score: int,
     *     rating: string,
     *     max_recommended_credit_limit_idr: int,
     *     payment_terms_days: int
     * }
     */
    public function computeScore(array $profile): array
    {
        $paidCount = (int) ($profile['paid_invoices_count'] ?? 0);
        $spendIdr = (int) ($profile['total_historical_spend_idr'] ?? 0);
        $avgDaysToPay = (float) ($profile['average_days_to_pay'] ?? 30.0);
        $lateCount = (int) ($profile['late_payments_count'] ?? 0);
        $disputeCount = (int) ($profile['disputed_invoices_count'] ?? 0);
        $years = (int) ($profile['years_in_business'] ?? 1);

        // 1. Komponen Kecepatan Bayar (Maksimal 350 poin)
        $speedScore = 350;
        if ($avgDaysToPay > 60) {
            $speedScore = 50;
        } elseif ($avgDaysToPay > 45) {
            $speedScore = 150;
        } elseif ($avgDaysToPay > 30) {
            $speedScore = 250;
        }

        // 2. Komponen Riwayat Transaksi & Nilai (Maksimal 300 poin)
        $spendScore = (int) min(300, ($spendIdr / 1000000000) * 150 + min(150, $paidCount * 5));

        // 3. Komponen Kestabilan Usaha (Maksimal 150 poin)
        $stabilityScore = (int) min(150, $years * 25);

        // 4. Penalti Keterlambatan dan Sengketa (-50 per late, -100 per dispute)
        $penalties = ($lateCount * 50) + ($disputeCount * 100);

        $finalScore = (int) max(100, min(1000, ($speedScore + $spendScore + $stabilityScore) - $penalties));

        $rating = 'C';
        $limit = 25000000;
        $terms = 14;

        if ($finalScore >= 800) {
            $rating = 'AAA_PRIME';
            $limit = 500000000;
            $terms = 45;
        } elseif ($finalScore >= 650) {
            $rating = 'A_EXCELLENT';
            $limit = 250000000;
            $terms = 30;
        } elseif ($finalScore >= 500) {
            $rating = 'BBB_GOOD';
            $limit = 100000000;
            $terms = 30;
        } elseif ($finalScore >= 350) {
            $rating = 'BB_MODERATE';
            $limit = 50000000;
            $terms = 14;
        }

        return [
            'score' => $finalScore,
            'rating' => $rating,
            'max_recommended_credit_limit_idr' => $limit,
            'payment_terms_days' => $terms,
        ];
    }
}
