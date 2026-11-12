<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Intercompany\Domain\DTOs\NettingMatrixResultDTO;

/**
 * Algoritma Optimasi Multilateral Netting Intercompany Treasury.
 *
 * Mengurangi aliran arus kas bruto (gross cash flows) antar-entitas konglomerasi
 * melalui eliminasi utang sirkular (graph cycle cancellation) dan kalkulasi posisi bersih multilateral.
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points) tanpa float.
 */
class BilateralNettedSettlementMatrixCalculator
{
    /**
     * Hitung matriks netting multilateral dan hasilkan instruksi penyelesaian kas optimal.
     *
     * @param array{
     *     session_code: string,
     *     settlement_period: string,
     *     base_currency?: string
     * } $config
     * @param array<int, array{
     *     from_entity: string,
     *     to_entity: string,
     *     amount_minor: int,
     *     currency?: string
     * }> $grossObligations
     * @param  array<string, int>  $exchangeRatesNumerator  Nilai tukar ke base currency (misal: USD -> 1600000 = Rp 16.000,00)
     * @param  array<string, int>  $exchangeRatesDenominator  Pembagi kurs (default 100)
     */
    public function calculate(
        array $config,
        array $grossObligations,
        array $exchangeRatesNumerator = ['IDR' => 100],
        array $exchangeRatesDenominator = ['IDR' => 100]
    ): NettingMatrixResultDTO {
        $sessionCode = $config['session_code'];
        $period = $config['settlement_period'];
        $baseCurrency = strtoupper($config['base_currency'] ?? 'IDR');

        if (empty($grossObligations)) {
            throw new InvalidArgumentException('Daftar kewajiban bruto intercompany tidak boleh kosong.');
        }

        // Kumpulkan semua entitas unik
        $entitiesMap = [];
        foreach ($grossObligations as $ob) {
            $from = strtoupper(trim($ob['from_entity']));
            $to = strtoupper(trim($ob['to_entity']));
            if ($from === $to) {
                throw new InvalidArgumentException("Entitas {$from} tidak dapat memiliki kewajiban terhadap dirinya sendiri.");
            }
            $entitiesMap[$from] = true;
            $entitiesMap[$to] = true;
        }

        $entities = array_keys($entitiesMap);
        sort($entities);

        // Bangun matriks kewajiban bruto awal terkonversi ke base currency (IDR)
        $matrix = [];
        foreach ($entities as $e1) {
            $matrix[$e1] = [];
            foreach ($entities as $e2) {
                $matrix[$e1][$e2] = 0;
            }
        }

        $totalGrossMinor = 0;

        foreach ($grossObligations as $ob) {
            $from = strtoupper(trim($ob['from_entity']));
            $to = strtoupper(trim($ob['to_entity']));
            $curr = strtoupper($ob['currency'] ?? 'IDR');
            $rawAmount = (int) $ob['amount_minor'];

            if ($rawAmount <= 0) {
                continue;
            }

            // Konversi ke mata uang dasar menggunakan rasio integer kurs
            $num = $exchangeRatesNumerator[$curr] ?? 100;
            $den = max(1, $exchangeRatesDenominator[$curr] ?? 100);
            $normalizedAmount = intdiv($rawAmount * $num, $den);

            $matrix[$from][$to] += $normalizedAmount;
            $totalGrossMinor += $normalizedAmount;
        }

        // Eliminasi Utang Timbal Balik Sederhana (Bilateral Netting: A->B vs B->A)
        $n = count($entities);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $e1 = $entities[$i];
                $e2 = $entities[$j];
                $debt12 = $matrix[$e1][$e2];
                $debt21 = $matrix[$e2][$e1];

                $offset = min($debt12, $debt21);
                if ($offset > 0) {
                    $matrix[$e1][$e2] -= $offset;
                    $matrix[$e2][$e1] -= $offset;
                }
            }
        }

        // Eliminasi Siklus Sirkular Multilateral Berulang (Cycle Cancellation)
        $this->cancelMultilateralCycles($matrix, $entities);

        // Hitung Saldo Bruto dan Posisi Bersih Multilateral Akhir
        $grossPayables = [];
        $grossReceivables = [];
        $netPositions = [];
        $sumNet = 0;

        foreach ($entities as $e) {
            $pay = 0;
            $rec = 0;

            foreach ($entities as $other) {
                $pay += $matrix[$e][$other];
                $rec += $matrix[$other][$e];
            }

            $grossPayables[$e] = $pay;
            $grossReceivables[$e] = $rec;
            $net = $rec - $pay;
            $netPositions[$e] = $net;
            $sumNet += $net;
        }

        // Verifikasi Invarian Konservasi Nol (Zero-Sum Invariant)
        if ($sumNet !== 0) {
            throw new InvalidArgumentException("Invarian zero-sum intercompany netting terlanggar! Selisih total: {$sumNet}");
        }

        // Susun Instruksi Kliring Kas Minimal (Matching Net Debtors dengan Net Creditors)
        $debtors = [];   // Posisi negatif (harus membayar)
        $creditors = []; // Posisi positif (harus menerima)

        foreach ($netPositions as $e => $pos) {
            if ($pos < 0) {
                $debtors[$e] = abs($pos);
            } elseif ($pos > 0) {
                $creditors[$e] = $pos;
            }
        }

        $instructions = [];
        $totalNetSettlementMinor = 0;
        $instructionIdx = 1;

        // Algoritma greedy dua penunjuk untuk meminimalkan jumlah transaksi kawat bank
        foreach ($debtors as $dName => &$dAmount) {
            foreach ($creditors as $cName => &$cAmount) {
                if ($dAmount <= 0) {
                    break;
                }
                if ($cAmount <= 0) {
                    continue;
                }

                $settle = min($dAmount, $cAmount);
                if ($settle > 0) {
                    $dAmount -= $settle;
                    $cAmount -= $settle;
                    $totalNetSettlementMinor += $settle;

                    $instructionCode = sprintf('NET-SETTLE-%s-%03d', $sessionCode, $instructionIdx++);
                    $instructions[] = [
                        'from_entity' => $dName,
                        'to_entity' => $cName,
                        'settlement_amount_minor' => $settle,
                        'currency' => $baseCurrency,
                        'clearing_bank_ref' => "CLR-BANK-{$dName}-{$cName}",
                        'settlement_instruction_code' => $instructionCode,
                    ];
                }
            }
        }

        $eliminatedDebt = max(0, $totalGrossMinor - $totalNetSettlementMinor);
        $efficiencyBps = $totalGrossMinor > 0
            ? intdiv($eliminatedDebt * 10000, $totalGrossMinor)
            : 0;

        $nettingMetrics = [
            'total_gross_obligations_minor' => $totalGrossMinor,
            'total_net_settlement_minor' => $totalNetSettlementMinor,
            'eliminated_circular_debt_minor' => $eliminatedDebt,
            'settlement_efficiency_ratio_bps' => $efficiencyBps,
            'is_zero_sum_balanced' => true,
        ];

        $nowStr = gmdate('Y-m-d\TH:i:s\Z');
        $signatureProof = hash(
            'sha256',
            "{$sessionCode}|{$period}|{$baseCurrency}|{$totalGrossMinor}|{$totalNetSettlementMinor}|{$efficiencyBps}"
        );

        return new NettingMatrixResultDTO(
            nettingSessionCode: $sessionCode,
            settlementPeriod: $period,
            baseCurrency: $baseCurrency,
            grossPayablesPerEntity: $grossPayables,
            grossReceivablesPerEntity: $grossReceivables,
            netPositionsPerEntity: $netPositions,
            settlementInstructions: $instructions,
            nettingMetrics: $nettingMetrics,
            computedAt: $nowStr,
            signatureProof: $signatureProof
        );
    }

    /**
     * Cari dan eliminasi siklus sirkular utang (A -> B -> C -> A) secara rekursif.
     *
     * @param  array<string, array<string, int>>  $matrix
     * @param  array<string>  $entities
     */
    protected function cancelMultilateralCycles(array &$matrix, array $entities): void
    {
        $maxPasses = 10;
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $cycleFound = false;

            foreach ($entities as $startNode) {
                $visited = [];
                $path = [];
                $cycle = $this->findCycleDfs($startNode, $startNode, $matrix, $visited, $path);

                if (! empty($cycle) && count($cycle) >= 3) {
                    // Cari nilai utang terkecil (bottleneck) di sepanjang siklus
                    $bottleneck = PHP_INT_MAX;
                    for ($k = 0; $k < count($cycle) - 1; $k++) {
                        $u = $cycle[$k];
                        $v = $cycle[$k + 1];
                        $bottleneck = min($bottleneck, $matrix[$u][$v]);
                    }

                    if ($bottleneck > 0 && $bottleneck < PHP_INT_MAX) {
                        for ($k = 0; $k < count($cycle) - 1; $k++) {
                            $u = $cycle[$k];
                            $v = $cycle[$k + 1];
                            $matrix[$u][$v] -= $bottleneck;
                        }
                        $cycleFound = true;
                    }
                }
            }

            if (! $cycleFound) {
                break;
            }
        }
    }

    /**
     * DFS sederhana untuk mendeteksi siklus berarah.
     *
     * @param  array<string, array<string, int>>  $matrix
     * @param  array<string, bool>  $visited
     * @param  array<string>  $path
     * @return array<string>
     */
    protected function findCycleDfs(
        string $origin,
        string $current,
        array &$matrix,
        array &$visited,
        array $path
    ): array {
        $path[] = $current;
        $visited[$current] = true;

        foreach ($matrix[$current] as $neighbor => $val) {
            if ($val <= 0) {
                continue;
            }

            if ($neighbor === $origin && count($path) >= 2) {
                $path[] = $neighbor;

                return $path;
            }

            if (! isset($visited[$neighbor])) {
                $res = $this->findCycleDfs($origin, $neighbor, $matrix, $visited, $path);
                if (! empty($res)) {
                    return $res;
                }
            }
        }

        unset($visited[$current]);

        return [];
    }
}
