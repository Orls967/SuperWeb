<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Metallurgy;

use InvalidArgumentException;

/**
 * M5 MassBalanceReconciler
 *
 * Merekonsiliasi neraca massa metalurgi (Feed = Konsentrat + Tailing)
 * dengan penyesuaian kuadrat terkecil berbobot varians instrumen.
 */
final class MassBalanceReconciler
{
    public function __construct(
        private readonly MassBalanceReconcilerCertificateVerifier $verifier = new MassBalanceReconcilerCertificateVerifier
    ) {}

    /**
     * Merekonsiliasi data logam terkandung feed vs produk hilir.
     *
     * @param  int  $feedKg  Kuantitas logam terkandung pada umpan (Feed)
     * @param  int  $concentrateKg  Kuantitas logam pada produk konsentrat
     * @param  int  $tailingKg  Kuantitas logam pada ampas (tailing)
     * @param  array{feed: int, concentrate: int, tailing: int}  $variances  Bobot varians pengukuran
     * @param  int  $toleranceKg  Batas toleransi diskrepansi sebelum audit/approval
     */
    public function reconcile(
        int $feedKg,
        int $concentrateKg,
        int $tailingKg,
        array $variances = ['feed' => 4, 'concentrate' => 1, 'tailing' => 3],
        int $toleranceKg = 500
    ): MassBalanceReconcilerResult {
        if ($feedKg <= 0 || $concentrateKg < 0 || $tailingKg < 0) {
            throw new InvalidArgumentException('Kuantitas feed, konsentrat, dan tailing tidak valid.');
        }

        $vFeed = max(1, $variances['feed'] ?? 4);
        $vConc = max(1, $variances['concentrate'] ?? 1);
        $vTail = max(1, $variances['tailing'] ?? 3);
        $sumV = $vFeed + $vConc + $vTail;

        // Discrepancy: r = Feed - (Concentrate + Tailing)
        $outputSum = $concentrateKg + $tailingKg;
        $r = $feedKg - $outputSum;
        $absR = abs($r);

        // Alokasi pembagian integer ke bawah
        $baseCorrFeed = intdiv($absR * $vFeed, $sumV);
        $baseCorrConc = intdiv($absR * $vConc, $sumV);
        $baseCorrTail = intdiv($absR * $vTail, $sumV);

        $allocatedSum = $baseCorrFeed + $baseCorrConc + $baseCorrTail;
        $remainder = $absR - $allocatedSum;

        // Sisa dialokasikan ke komponen varians terbesar (tie-break: feed, concentrate, tailing)
        $components = [
            ['name' => 'feed', 'var' => $vFeed, 'corr' => &$baseCorrFeed],
            ['name' => 'concentrate', 'var' => $vConc, 'corr' => &$baseCorrConc],
            ['name' => 'tailing', 'var' => $vTail, 'corr' => &$baseCorrTail],
        ];

        usort($components, static fn (array $a, array $b) => $b['var'] <=> $a['var']);

        for ($i = 0; $i < $remainder; $i++) {
            $components[$i % 3]['corr']++;
        }

        // Terapkan penyesuaian:
        // Jika r > 0 (Feed berlebih), Feed dikurangi, output ditambah
        // Jika r < 0 (Output berlebih), Feed ditambah, output dikurangi
        if ($r >= 0) {
            $adjFeed = $feedKg - $baseCorrFeed;
            $adjConc = $concentrateKg + $baseCorrConc;
            $adjTail = $tailingKg + $baseCorrTail;
        } else {
            $adjFeed = $feedKg + $baseCorrFeed;
            $adjConc = $concentrateKg - $baseCorrConc;
            $adjTail = $tailingKg - $baseCorrTail;
        }

        // Koreksi sisa pembulatan terakhir untuk menjamin kekekalan massa eksak
        $finalDiscrepancy = $adjFeed - ($adjConc + $adjTail);
        if ($finalDiscrepancy !== 0) {
            $adjConc += $finalDiscrepancy;
        }

        $recoveryBps = intdiv($adjConc * 10000, $adjFeed);
        $requiresInvestigation = $absR > $toleranceKg;
        $requiresApproval = $requiresInvestigation;

        $payload = sprintf(
            '%d|%d|%d|%d|%d|%d|%d|%d|%s|%s',
            $feedKg,
            $concentrateKg,
            $tailingKg,
            $adjFeed,
            $adjConc,
            $adjTail,
            $r,
            $recoveryBps,
            $requiresInvestigation ? '1' : '0',
            $requiresApproval ? '1' : '0'
        );

        $certificate = [
            'canonical_hash' => hash('sha256', $payload),
            'conservation_law_verified' => true,
            'verified_at' => now()->toIso8601String(),
        ];

        $result = new MassBalanceReconcilerResult(
            rawFeedKg: $feedKg,
            rawConcentrateKg: $concentrateKg,
            rawTailingKg: $tailingKg,
            reconciledFeedKg: $adjFeed,
            reconciledConcentrateKg: $adjConc,
            reconciledTailingKg: $adjTail,
            discrepancyKg: $r,
            recoveryBps: $recoveryBps,
            requiresInvestigation: $requiresInvestigation,
            requiresApproval: $requiresApproval,
            certificate: $certificate
        );

        $this->verifier->verify($result);

        return $result;
    }
}
