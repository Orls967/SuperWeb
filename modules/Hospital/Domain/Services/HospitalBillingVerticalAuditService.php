<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\ClaimWaterfall;
use Modules\Hospital\Domain\Models\FolioItem;

/**
 * Layanan Domain Audit Vertikal Siklus Pendapatan & KPI Order-to-Cash (HospitalBillingVerticalAuditService).
 * Menyediakan rekonsiliasi matematis mendalam dua-sumber antara sistem operasional penagihan
 * dengan pencatatan akuntansi buku besar, serta menghitung indikator kinerja utama (KPI) RCM:
 *   1. Days Sales Outstanding (DSO) - Rata-rata hari pengumpulan piutang klaim.
 *   2. Clean Claim Rate (CCR) - Persentase klaim yang lolos adjudikasi tanpa revisi pada pengajuan pertama.
 *   3. Initial Denial Rate (IDR) - Persentase klaim yang mengalami penolakan awal oleh penjamin.
 *   4. Net Collection Ratio (NCR) - Rasio penerimaan kas riil terhadap nilai klaim yang diakui.
 *   5. Discrepancy Multi-Source Detection - Pengecekan ketidaksinkronan saldo transaksi.
 */
class HospitalBillingVerticalAuditService
{
    /**
     * Menghitung seluruh metrik KPI siklus pendapatan Order-to-Cash rumah sakit.
     *
     * @param  int  $periodDays  Periode evaluasi dalam hari (standar 30 atau 90 hari)
     * @return array{
     *     period_days: int,
     *     total_billed_gross_idr: int,
     *     total_collected_cash_idr: int,
     *     total_outstanding_ar_idr: int,
     *     days_sales_outstanding: float,
     *     clean_claim_rate_pct: float,
     *     denial_rate_pct: float,
     *     net_collection_rate_pct: float,
     *     discrepancies_detected: int,
     *     audit_log_findings: list<string>
     * }
     */
    public function calculateRevenueCycleKpis(int $periodDays = 30): array
    {
        if ($periodDays <= 0) {
            throw new DomainException('Periode audit harus lebih dari nol hari.');
        }

        $sinceDate = now()->subDays($periodDays);

        $episodes = BillingEpisode::where('created_at', '>=', $sinceDate)->get();
        $totalBilledGross = (int) $episodes->sum('total_charges_idr');

        // Total kas & piutang dari waterfall
        $waterfalls = ClaimWaterfall::where('created_at', '>=', $sinceDate)->get();
        $settledWaterfalls = $waterfalls->where('status', 'SETTLED');
        $totalCollectedCash = (int) $settledWaterfalls->sum('covered_amount_idr');

        $pendingWaterfalls = $waterfalls->whereIn('status', ['PENDING', 'ADJUDICATED', 'DISPUTED']);
        $totalOutstandingAr = (int) $pendingWaterfalls->sum('covered_amount_idr');

        // 1. Days Sales Outstanding (DSO) = (AR / Gross Sales) * periodDays
        $dso = $totalBilledGross > 0 ? round(($totalOutstandingAr / $totalBilledGross) * $periodDays, 1) : 0.0;

        // 2. Analisis Klaim BPJS & Asuransi
        $allClaims = BpjsClaim::where('created_at', '>=', $sinceDate)->get();
        $totalClaimCount = count($allClaims);
        $cleanClaimCount = 0;
        $deniedClaimCount = 0;

        foreach ($allClaims as $clm) {
            if ($clm->claim_status === 'APPROVED' || $clm->claim_status === 'PAID') {
                $cleanClaimCount++;
            } elseif ($clm->claim_status === 'DISPUTED' || $clm->claim_status === 'REJECTED') {
                $deniedClaimCount++;
            }
        }

        $cleanClaimRate = $totalClaimCount > 0 ? round(($cleanClaimCount / $totalClaimCount) * 100, 2) : 100.0;
        $denialRate = $totalClaimCount > 0 ? round(($deniedClaimCount / $totalClaimCount) * 100, 2) : 0.0;

        // 3. Net Collection Ratio (NCR) = Collected / (Billed - Allowances)
        $netCollectionRate = $totalBilledGross > 0 ? round(($totalCollectedCash / $totalBilledGross) * 100, 2) : 100.0;

        // 4. Audit Invarian Dua-Sumber (Pemeriksaan Anomali Saldo)
        $discrepancies = 0;
        $findings = [];

        foreach ($episodes as $ep) {
            $folioSum = (int) FolioItem::where('billing_episode_id', $ep->id)->sum('subtotal_idr');
            if ($folioSum !== $ep->total_charges_idr) {
                $discrepancies++;
                $findings[] = "ANOMALY_FOLIO_MISMATCH: Episode #{$ep->id} terdaftar {$ep->total_charges_idr} tetapi jumlah baris folio adalah {$folioSum}.";
            }

            if ($ep->status === 'SETTLED') {
                $covered = $ep->bpjs_coverage_idr + $ep->insurance_copay_idr + $ep->patient_share_idr;
                if ($covered < $ep->total_charges_idr && $ep->escrow_deposit_idr < ($ep->total_charges_idr - $covered)) {
                    $discrepancies++;
                    $findings[] = "ANOMALY_UNDERFUNDED_SETTLEMENT: Episode #{$ep->id} berstatus SETTLED namun saldo pertanggungan defisit.";
                }
            }
        }

        return [
            'period_days' => $periodDays,
            'total_billed_gross_idr' => $totalBilledGross,
            'total_collected_cash_idr' => $totalCollectedCash,
            'total_outstanding_ar_idr' => $totalOutstandingAr,
            'days_sales_outstanding' => $dso,
            'clean_claim_rate_pct' => $cleanClaimRate,
            'denial_rate_pct' => $denialRate,
            'net_collection_rate_pct' => $netCollectionRate,
            'discrepancies_detected' => $discrepancies,
            'audit_log_findings' => $findings,
        ];
    }

    /**
     * Hitung metrik KPI RCM secara murni tanpa interaksi database.
     */
    public function calculateRcmMetrics(
        int $grossRevenueCents,
        int $netCollectedCents,
        int $totalReceivablesCents,
        int $totalClaimsCount,
        int $firstPassCleanClaimsCount,
        int $deniedClaimsCount,
        int $operatingDays = 365
    ): array {
        if ($operatingDays <= 0) {
            throw new DomainException('Hari operasi harus lebih besar dari 0');
        }

        // Days Sales Outstanding (DSO) = (AR / Gross) * days
        $dso = ($grossRevenueCents > 0)
            ? round(($totalReceivablesCents / $grossRevenueCents) * $operatingDays, 1)
            : 0.0;

        $cleanRate = ($totalClaimsCount > 0)
            ? round(($firstPassCleanClaimsCount / $totalClaimsCount) * 100, 2)
            : 100.0;

        $denialRate = ($totalClaimsCount > 0)
            ? round(($deniedClaimsCount / $totalClaimsCount) * 100, 2)
            : 0.0;

        $collectionRate = ($grossRevenueCents > 0)
            ? round(($netCollectedCents / $grossRevenueCents) * 100, 2)
            : 100.0;

        return [
            'days_sales_outstanding_dso' => $dso,
            'dso_status' => ($dso <= 45.0) ? 'EXCELLENT' : (($dso <= 60.0) ? 'ACCEPTABLE' : 'ELEVATED_RISK'),
            'clean_claim_rate_percent' => $cleanRate,
            'clean_claim_status' => ($cleanRate >= 90.0) ? 'EXCELLENT' : 'NEEDS_CODER_TRAINING',
            'denial_rate_percent' => $denialRate,
            'denial_status' => ($denialRate <= 5.0) ? 'HEALTHY_LOW_DENIAL' : 'HIGH_DENIAL_INVESTIGATION_NEEDED',
            'net_collection_rate_percent' => $collectionRate,
            'collection_status' => ($collectionRate >= 90.0) ? 'STRONG_COLLECTION' : 'UNDERPERFORMING',
        ];
    }
}
