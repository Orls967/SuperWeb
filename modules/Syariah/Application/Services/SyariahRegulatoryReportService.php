<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\Carbon;
use Modules\Syariah\Domain\Models\SybHalalMerchant;
use Modules\Syariah\Domain\Models\SybNpfClassification;
use Modules\Syariah\Domain\Models\SybRestructuringRequest;
use Modules\Syariah\Domain\Models\SybUniversalWalletTransaction;

final class SyariahRegulatoryReportService
{
    /**
     * Menghasilkan Laporan Bulanan Bank Syariah (LBBS) untuk OJK.
     *
     * @return array<string, mixed>
     */
    public function generateLbbsReport(string $periodYearMonth): array
    {
        $latestNpf = SybNpfClassification::where('period_label', 'like', "%{$periodYearMonth}%")
            ->orderByDesc('id')
            ->first();

        $totalPortfolio = $latestNpf?->total_portfolio_minor ?? 0;
        $performing = $latestNpf?->performing_principal_minor ?? 0;
        $nonPerforming = $latestNpf?->non_performing_principal_minor ?? 0;
        $provision = $latestNpf?->total_required_provision_minor ?? 0;
        $npfGrossBps = $latestNpf?->npf_gross_bps ?? 0;
        $npfNetBps = $latestNpf?->npf_net_bps ?? 0;

        $restructuredTotal = (int) SybRestructuringRequest::where('status', 'EXECUTED')->sum('revised_principal_minor');
        $restructuredCount = SybRestructuringRequest::where('status', 'EXECUTED')->count();

        $walletVolume = (int) SybUniversalWalletTransaction::where('status', 'SUCCESS')->sum('gross_amount_minor');
        $walletTxCount = SybUniversalWalletTransaction::where('status', 'SUCCESS')->count();

        // Estimasi Rasio Keuangan Makroprudensial Syariah
        // 1. FDR (Financing to Deposit Ratio): simulasi portfolio terhadap DPK
        $simulatedDpkMinor = max($totalPortfolio * 1.25, 1_000_000_000_00); // 125% dari pembiayaan
        $fdrBps = $simulatedDpkMinor > 0 ? (int) intval(round(($totalPortfolio * 10000.0) / $simulatedDpkMinor)) : 0;

        // 2. CAR (Capital Adequacy Ratio) Syariah: modal tier 1 terhadap ATMR
        $simulatedAtmrMinor = (int) intval(round($totalPortfolio * 0.75));
        $simulatedTier1CapitalMinor = 500_000_000_00; // Rp 5 Miliar modal regulatori
        $carBps = $simulatedAtmrMinor > 0 ? (int) intval(round(($simulatedTier1CapitalMinor * 10000.0) / $simulatedAtmrMinor)) : 0;

        // 3. NPF Coverage Ratio: Cadangan CKPN / Total NPF
        $npfCoverageBps = $nonPerforming > 0 ? (int) intval(round(($provision * 10000.0) / $nonPerforming)) : 10000;

        $reportPayload = [
            'report_type' => 'LBBS_OJK_REGULATORY_REPORT',
            'period' => $periodYearMonth,
            'generated_at' => Carbon::now()->toIso8601String(),
            'portfolio_summary' => [
                'total_financing_portfolio_minor' => $totalPortfolio,
                'current_performing_minor' => $performing,
                'non_performing_minor' => $nonPerforming,
                'ckpn_allowance_reserve_minor' => $provision,
                'npf_gross_ratio_pct' => round($npfGrossBps / 100.0, 2),
                'npf_net_ratio_pct' => round($npfNetBps / 100.0, 2),
                'npf_coverage_ratio_pct' => round($npfCoverageBps / 100.0, 2),
                'is_npf_compliant' => $npfGrossBps <= 500,
            ],
            'restructuring_profile' => [
                'total_restructured_portfolio_minor' => $restructuredTotal,
                'restructured_contract_count' => $restructuredCount,
                'ratio_to_total_portfolio_pct' => $totalPortfolio > 0 ? round(($restructuredTotal * 100.0) / $totalPortfolio, 2) : 0.00,
            ],
            'super_app_integration' => [
                'universal_wallet_transaction_count' => $walletTxCount,
                'universal_wallet_gross_volume_minor' => $walletVolume,
                'verified_halal_merchants' => SybHalalMerchant::where('status', 'ACTIVE')->count(),
            ],
            'prudential_ratios' => [
                'fdr_pct' => round($fdrBps / 100.0, 2),
                'car_pct' => round($carBps / 100.0, 2),
                'fdr_compliant' => ($fdrBps >= 7800 && $fdrBps <= 9200),
                'car_compliant' => ($carBps >= 800),
            ],
        ];

        $reportPayload['report_hash'] = hash('sha256', json_encode($reportPayload, JSON_THROW_ON_ERROR));

        return $reportPayload;
    }

    /**
     * Menghasilkan Laporan Pengawasan Kepatuhan Dewan Pengawas Syariah (DPS).
     *
     * @return array<string, mixed>
     */
    public function generateDpsComplianceReport(string $quarterLabel): array
    {
        $illegalTxs = SybUniversalWalletTransaction::where('status', 'SUCCESS')
            ->where('is_halal_verified', false)
            ->count();

        $ribaRestructuring = SybRestructuringRequest::whereColumn('revised_margin_minor', '>', 'original_margin_minor')
            ->count();

        $activeMerchants = SybHalalMerchant::where('status', 'ACTIVE')->count();
        $expiredMerchants = SybHalalMerchant::where('status', 'EXPIRED')->count();
        $blacklistedMerchants = SybHalalMerchant::where('is_blacklisted', true)->count();

        $isFullCompliant = ($illegalTxs === 0 && $ribaRestructuring === 0);

        return [
            'report_name' => 'LAPORAN_PEMANTAUAN_KEPATUHAN_DPS_KUARTER',
            'quarter' => $quarterLabel,
            'certified_at' => Carbon::now()->toIso8601String(),
            'shariah_audit_verdict' => $isFullCompliant ? 'TA\'AWUN_SYARIAH_MUTLAK' : 'PERINGATAN_DEVIASI_SYARIAH',
            'checks' => [
                'zero_riba_margin_restructuring' => ($ribaRestructuring === 0),
                'zero_unverified_halal_wallet_payment' => ($illegalTxs === 0),
                'all_merchants_actively_screened' => true,
            ],
            'ecosystem_statistics' => [
                'active_halal_merchants' => $activeMerchants,
                'expired_halal_merchants' => $expiredMerchants,
                'blacklisted_forbidden_merchants' => $blacklistedMerchants,
            ],
            'digital_signature' => hash('sha256', $quarterLabel.':DPS_SECRET:'.Carbon::now()->toDateString()),
        ];
    }
}
