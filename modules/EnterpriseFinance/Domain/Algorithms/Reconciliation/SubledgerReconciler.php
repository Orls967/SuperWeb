<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Reconciliation;

use InvalidArgumentException;

/**
 * Algoritma Rekonsiliasi Dua Sumber Subledger vs Buku Besar (General Ledger).
 * Mengisolasi pos gantung, selisih pembulatan, dan menerbitkan sertifikat kepatuhan tutup buku.
 */
final class SubledgerReconciler
{
    /**
     * Memverifikasi keseimbangan mutasi debit dan kredit pada kumpulan baris jurnal.
     *
     * @param list<array{debit_minor: int, credit_minor: int, account: string}> $entries
     * @return array{
     *     is_balanced: bool,
     *     total_debit_minor: int,
     *     total_credit_minor: int,
     *     net_discrepancy_minor: int
     * }
     */
    public static function verifyLedgerBalance(array $entries): array
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            $debit = (int) ($e['debit_minor'] ?? 0);
            $credit = (int) ($e['credit_minor'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Baris jurnal dilarang memuat nilai negatif.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $discrepancy = abs($totalDebit - $totalCredit);

        return [
            'is_balanced' => ($discrepancy === 0),
            'total_debit_minor' => $totalDebit,
            'total_credit_minor' => $totalCredit,
            'net_discrepancy_minor' => $discrepancy,
        ];
    }

    /**
     * Menandingkan total saldo akun buku besar terhadap total agregasi subledger operasional.
     *
     * @param int $generalLedgerBalanceMinor Saldo menurut buku besar (minor IDR).
     * @param int $subledgerTotalMinor Saldo menurut subledger operasional (minor IDR).
     * @param string $subledgerName Nama domain subledger (misal: 'AR', 'AP', 'INVENTORY', 'ASSETS').
     * @param int $toleranceMinor Batas toleransi selisih dalam minor unit (default 0).
     * @return array{
     *     is_reconciled: bool,
     *     gl_balance_minor: int,
     *     subledger_total_minor: int,
     *     variance_minor: int,
     *     variance_percent_bps: int,
     *     status: string,
     *     certificate_hash: string
     * }
     */
    public static function reconcileDomain(
        int $generalLedgerBalanceMinor,
        int $subledgerTotalMinor,
        string $subledgerName,
        int $toleranceMinor = 0
    ): array {
        $variance = $generalLedgerBalanceMinor - $subledgerTotalMinor;
        $absVariance = abs($variance);
        $isReconciled = ($absVariance <= $toleranceMinor);

        $base = max(abs($generalLedgerBalanceMinor), abs($subledgerTotalMinor));
        $bps = $base > 0 ? intdiv($absVariance * 10000, $base) : 0;

        $status = match (true) {
            $absVariance === 0 => 'PERFECT_MATCH',
            $isReconciled => 'WITHIN_TOLERANCE',
            $absVariance > 0 && $bps <= 100 => 'MINOR_DISCREPANCY',
            default => 'MATERIAL_BREACH',
        };

        $rawCert = "{$subledgerName}|{$generalLedgerBalanceMinor}|{$subledgerTotalMinor}|{$status}";
        $certHash = hash('sha256', $rawCert);

        return [
            'is_reconciled' => $isReconciled,
            'gl_balance_minor' => $generalLedgerBalanceMinor,
            'subledger_total_minor' => $subledgerTotalMinor,
            'variance_minor' => $variance,
            'variance_percent_bps' => $bps,
            'status' => $status,
            'certificate_hash' => $certHash,
        ];
    }

    /**
     * Memindai daftar faktur/transaksi subledger individual dan mendeteksi pos gantung yang belum tercatat di GL.
     *
     * @param list<array{reference: string, amount_minor: int}> $subledgerItems
     * @param list<string> $recordedGlReferences
     * @return array{
     *     unposted_count: int,
     *     unposted_total_minor: int,
     *     unposted_references: list<string>
     * }
     */
    public static function detectUnpostedSubledgerItems(
        array $subledgerItems,
        array $recordedGlReferences
    ): array {
        $glRefSet = array_fill_keys($recordedGlReferences, true);
        $unpostedRefs = [];
        $unpostedTotal = 0;

        foreach ($subledgerItems as $item) {
            $ref = (string) ($item['reference'] ?? '');
            $amt = (int) ($item['amount_minor'] ?? 0);

            if (! isset($glRefSet[$ref])) {
                $unpostedRefs[] = $ref;
                $unpostedTotal += $amt;
            }
        }

        return [
            'unposted_count' => count($unpostedRefs),
            'unposted_total_minor' => $unpostedTotal,
            'unposted_references' => $unpostedRefs,
        ];
    }

    /**
     * Memverifikasi nomor seri transaksi gapless (ketiadaan nomor yang terlewat).
     *
     * @param list<string> $sequenceStrings Nomor seri format terstruktur, misal '010.001-26.00000001'
     * @param string $prefixPrefix Bagian prefiks yang diharapkan (misal '010.001-26.')
     * @return array{
     *     is_gapless: bool,
     *     expected_count: int,
     *     actual_count: int,
     *     missing_sequences: list<int>,
     *     duplicate_sequences: list<int>
     * }
     */
    public static function verifyGaplessSequence(array $sequenceStrings, string $prefixPrefix): array
    {
        if (empty($sequenceStrings)) {
            return [
                'is_gapless' => true,
                'expected_count' => 0,
                'actual_count' => 0,
                'missing_sequences' => [],
                'duplicate_sequences' => [],
            ];
        }

        $numbers = [];
        $duplicates = [];
        $seen = [];

        foreach ($sequenceStrings as $str) {
            if (! str_starts_with($str, $prefixPrefix)) {
                continue;
            }
            $numPart = substr($str, strlen($prefixPrefix));
            $num = (int) $numPart;

            if (isset($seen[$num])) {
                $duplicates[] = $num;
            } else {
                $seen[$num] = true;
                $numbers[] = $num;
            }
        }

        sort($numbers);
        $min = reset($numbers);
        $max = end($numbers);
        $missing = [];

        for ($i = $min; $i <= $max; $i++) {
            if (! isset($seen[$i])) {
                $missing[] = $i;
            }
        }

        $isGapless = empty($missing) && empty($duplicates);

        return [
            'is_gapless' => $isGapless,
            'expected_count' => count($numbers) + count($missing),
            'actual_count' => count($numbers),
            'missing_sequences' => $missing,
            'duplicate_sequences' => array_values(array_unique($duplicates)),
        ];
    }
}

