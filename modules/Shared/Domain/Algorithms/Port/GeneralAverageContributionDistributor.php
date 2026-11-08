<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Port;

/**
 * A14: General Average Contribution Distributor Engine.
 * 
 * Standar: York-Antwerp Rules 2016 (Comité Maritime International / CMI).
 * Menghitung alokasi pembagian kerugian dan pengorbanan demi keselamatan bersama
 * (General Average Sacrifices & Extraordinary Expenditures) terhadap seluruh
 * nilai kontributif pihak yang berkepentingan (kapal, kargo, freight).
 */
final class GeneralAverageContributionDistributor
{
    /**
     * Hitung penyesuaian General Average (Average Adjustment Statement).
     *
     * @param float $shipSoundValueUsd Nilai pasar wajar kapal dalam kondisi selamat
     * @param float $shipParticularAverageDamageUsd Kerusakan khusus kapal non-GA
     * @param float $cargoCommercialValueUsd Nilai faktur komersial kargo di pelabuhan tujuan
     * @param float $cargoParticularAverageDamageUsd Kerusakan khusus kargo non-GA
     * @param float $freightAtRiskUsd Uang tambang yang belum terbayar di muka (at owner's risk)
     * @param float $freightPortExpensesSavedUsd Pengeluaran pelabuhan yang dihemat
     * @param array<int, array{
     *     interest: string, // SHIP, CARGO, FREIGHT
     *     description: string,
     *     amount_usd: float,
     *     category: string // SACRIFICE, EXPENDITURE
     * }> $losses
     * @return array{
     *     ship_contributory_value: float,
     *     cargo_contributory_value: float,
     *     freight_contributory_value: float,
     *     total_contributory_value: float,
     *     total_sacrifices: float,
     *     total_expenditures: float,
     *     total_general_average_loss: float,
     *     contribution_rate_percentage: float,
     *     apportionment: array{
     *         ship: array{
     *             contributory_value: float,
     *             gross_contribution: float,
     *             loss_made_good: float,
     *             net_payable_or_receivable: float
     *         },
     *         cargo: array{
     *             contributory_value: float,
     *             gross_contribution: float,
     *             loss_made_good: float,
     *             net_payable_or_receivable: float
     *         },
     *         freight: array{
     *             contributory_value: float,
     *             gross_contribution: float,
     *             loss_made_good: float,
     *             net_payable_or_receivable: float
     *         }
     *     }
     * }
     */
    public function calculateAdjustment(
        float $shipSoundValueUsd,
        float $shipParticularAverageDamageUsd,
        float $cargoCommercialValueUsd,
        float $cargoParticularAverageDamageUsd,
        float $freightAtRiskUsd,
        float $freightPortExpensesSavedUsd,
        array $losses
    ): array {
        // 1. Akumulasi GA Losses menurut pihak yang menderita (Loss Made Good)
        // Menurut York-Antwerp Rule XVII: Hanya pengorbanan fisik (Sacrifices) yang ditambahkan
        // kembali ke nilai kontributif (Amount Made Good as GA for property sacrificed).
        // Biaya pengeluaran (Expenditures, misal salvage/port of refuge) tidak menambah nilai kapal/kargo.
        $totalSacrifices = 0.0;
        $totalExpenditures = 0.0;
        $sacrificesByInterest = ['SHIP' => 0.0, 'CARGO' => 0.0, 'FREIGHT' => 0.0];
        $totalLossesByInterest = ['SHIP' => 0.0, 'CARGO' => 0.0, 'FREIGHT' => 0.0];

        foreach ($losses as $loss) {
            $amt = $loss['amount_usd'];
            $interest = strtoupper($loss['interest']);

            if ($loss['category'] === 'SACRIFICE') {
                $totalSacrifices += $amt;
                if (isset($sacrificesByInterest[$interest])) {
                    $sacrificesByInterest[$interest] += $amt;
                }
            } else {
                $totalExpenditures += $amt;
            }

            if (isset($totalLossesByInterest[$interest])) {
                $totalLossesByInterest[$interest] += $amt;
            }
        }

        $totalGaLoss = $totalSacrifices + $totalExpenditures;

        // 2. Nilai Kontributif menurut York-Antwerp Rules:
        // Nilai Kontributif Kapal = Sound Value - Kerusakan Partikular + Pengorbanan GA Kapal (Made Good)
        $shipContributory = max(0.0, $shipSoundValueUsd - $shipParticularAverageDamageUsd) + $sacrificesByInterest['SHIP'];

        // Nilai Kontributif Kargo = Arrived Sound Value - Kerusakan Partikular + Pengorbanan GA Kargo (Made Good)
        $cargoContributory = max(0.0, $cargoCommercialValueUsd - $cargoParticularAverageDamageUsd) + $sacrificesByInterest['CARGO'];

        // Nilai Kontributif Freight = Freight at Risk - Expenses Saved + Pengorbanan GA Freight
        $freightContributory = max(0.0, $freightAtRiskUsd - $freightPortExpensesSavedUsd) + $sacrificesByInterest['FREIGHT'];

        $totalContributory = max(1.0, $shipContributory + $cargoContributory + $freightContributory);

        // 3. Rasio Kontribusi:
        $contributionRate = ($totalGaLoss / $totalContributory);

        // 4. Perhitungan Kontribusi Masing-masing Pihak:
        $shipGross = $shipContributory * $contributionRate;
        $cargoGross = $cargoContributory * $contributionRate;
        $freightGross = $freightContributory * $contributionRate;

        // Net Payable (positif) atau Net Receivable (negatif):
        $shipNet = $shipGross - $totalLossesByInterest['SHIP'];
        $cargoNet = $cargoGross - $totalLossesByInterest['CARGO'];
        $freightNet = $freightGross - $totalLossesByInterest['FREIGHT'];

        return [
            'ship_contributory_value' => round($shipContributory, 2),
            'cargo_contributory_value' => round($cargoContributory, 2),
            'freight_contributory_value' => round($freightContributory, 2),
            'total_contributory_value' => round($totalContributory, 2),
            'total_sacrifices' => round($totalSacrifices, 2),
            'total_expenditures' => round($totalExpenditures, 2),
            'total_general_average_loss' => round($totalGaLoss, 2),
            'contribution_rate_percentage' => round($contributionRate * 100.0, 4),
            'apportionment' => [
                'ship' => [
                    'contributory_value' => round($shipContributory, 2),
                    'gross_contribution' => round($shipGross, 2),
                    'loss_made_good' => round($totalLossesByInterest['SHIP'], 2),
                    'net_payable_or_receivable' => round($shipNet, 2),
                ],
                'cargo' => [
                    'contributory_value' => round($cargoContributory, 2),
                    'gross_contribution' => round($cargoGross, 2),
                    'loss_made_good' => round($totalLossesByInterest['CARGO'], 2),
                    'net_payable_or_receivable' => round($cargoNet, 2),
                ],
                'freight' => [
                    'contributory_value' => round($freightContributory, 2),
                    'gross_contribution' => round($freightGross, 2),
                    'loss_made_good' => round($totalLossesByInterest['FREIGHT'], 2),
                    'net_payable_or_receivable' => round($freightNet, 2),
                ],
            ],
        ];
    }
}

