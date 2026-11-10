<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Algorithms;

use Modules\Treasury\Domain\Algorithms\Certificates\BankReconciliationCertificate;
use Modules\Treasury\Domain\DTOs\BankReconciliationResultDTO;
use Modules\Treasury\Domain\DTOs\BankStatementLineDTO;
use Modules\Treasury\Domain\DTOs\BookLedgerLineDTO;
use Modules\Treasury\Domain\DTOs\ExactMatchDTO;
use Modules\Treasury\Domain\DTOs\ManyToOneMatchDTO;
use Modules\Treasury\Domain\DTOs\ToleranceMatchDTO;

/**
 * Algoritma D2: Pemadanan Rekonsiliasi Bank Tiga Tahap Deterministik.
 *
 * Tahap 1: Exact Match (amount identik & ref tidak kosong sama).
 * Tahap 2: Tolerance Match (|delta amount| <= 500 minor & |delta days| <= 3 hari).
 * Tahap 3: Many-to-One Match (subset 2..4 mutasi buku = 1 mutasi bank, maks 12 kandidat).
 *
 * Kompleksitas Waktu: O(N_B log N_B + N_L log N_L + N_B * 781)
 * Kompleksitas Ruang: O(N_B + N_L)
 */
final class BankReconciliationMatcher
{
    /**
     * @param  list<BankStatementLineDTO>  $bankLines
     * @param  list<BookLedgerLineDTO>  $bookLines
     */
    public function match(array $bankLines, array $bookLines): BankReconciliationResultDTO
    {
        $exactMatches = [];
        $toleranceMatches = [];
        $manyToOneMatches = [];

        /** @var array<string, BankStatementLineDTO> $remBank */
        $remBank = [];
        foreach ($bankLines as $b) {
            $remBank[$b->id] = $b;
        }

        /** @var array<string, BookLedgerLineDTO> $remBook */
        $remBook = [];
        foreach ($bookLines as $l) {
            $remBook[$l->id] = $l;
        }

        // --- TAHAP 1: EXACT MATCH ---
        // Urutkan id bank agar deterministik
        ksort($remBank);
        foreach ($remBank as $bId => $bank) {
            if ($bank->reference === '') {
                continue;
            }
            foreach ($remBook as $lId => $book) {
                if ($book->reference === '') {
                    continue;
                }
                if ($bank->amount === $book->amount && $bank->reference === $book->reference) {
                    $exactMatches[] = new ExactMatchDTO(
                        bankId: $bId,
                        bookId: $lId,
                        amount: $bank->amount,
                        reference: $bank->reference
                    );
                    unset($remBank[$bId], $remBook[$lId]);
                    break;
                }
            }
        }

        // --- TAHAP 2: TOLERANSI MATCH ---
        // |delta amount| <= 500 minor dan |delta day| <= 3 hari
        // Berpasangan menurut urutan id bank dengan kunci (|selisih amount|, |selisih day|, id buku)
        ksort($remBank);
        foreach ($remBank as $bId => $bank) {
            $bestCandidateId = null;
            $bestAmountDiff = 999999999;
            $bestDayDiff = 999999999;

            foreach ($remBook as $lId => $book) {
                $amountDiff = abs($bank->amount - $book->amount);
                $dayDiff = abs($bank->day - $book->day);

                if ($amountDiff <= 500 && $dayDiff <= 3) {
                    if ($amountDiff < $bestAmountDiff
                        || ($amountDiff === $bestAmountDiff && $dayDiff < $bestDayDiff)
                        || ($amountDiff === $bestAmountDiff && $dayDiff === $bestDayDiff && ($bestCandidateId === null || strcmp($lId, $bestCandidateId) < 0))) {
                        $bestCandidateId = $lId;
                        $bestAmountDiff = $amountDiff;
                        $bestDayDiff = $dayDiff;
                    }
                }
            }

            if ($bestCandidateId !== null) {
                $toleranceMatches[] = new ToleranceMatchDTO(
                    bankId: $bId,
                    bookId: $bestCandidateId,
                    bankAmount: $bank->amount,
                    bookAmount: $remBook[$bestCandidateId]->amount,
                    discrepancyAmount: $bestAmountDiff,
                    dayDifference: $bestDayDiff
                );
                unset($remBank[$bId], $remBook[$bestCandidateId]);
            }
        }

        // --- TAHAP 3: MANY-TO-ONE MATCH ---
        // Untuk tiap baris bank tersisa berjumlah positif (urut id),
        // kandidat = baris buku tersisa bertanda sama, jumlah < jumlah bank, |selisih hari| <= 5,
        // maks 12 kandidat urut (hari, id).
        // Cari himpunan berukuran 2..4 yang jumlahnya persis sama.
        // Ukuran terkecil dulu, lalu urutan id leksikografis terkecil.
        ksort($remBank);
        foreach ($remBank as $bId => $bank) {
            if ($bank->amount <= 0) {
                continue;
            }

            /** @var list<BookLedgerLineDTO> $candidates */
            $candidates = [];
            foreach ($remBook as $book) {
                if ($book->amount > 0 && $book->amount < $bank->amount && abs($bank->day - $book->day) <= 5) {
                    $candidates[] = $book;
                }
            }

            usort($candidates, function (BookLedgerLineDTO $a, BookLedgerLineDTO $b) {
                if ($a->day !== $b->day) {
                    return $a->day <=> $b->day;
                }

                return strcmp($a->id, $b->id);
            });

            if (count($candidates) > 12) {
                $candidates = array_slice($candidates, 0, 12);
            }

            $matchedSubset = $this->findSubsetSum($candidates, $bank->amount);
            if ($matchedSubset !== null) {
                $bookIds = array_map(fn (BookLedgerLineDTO $c) => $c->id, $matchedSubset);
                $manyToOneMatches[] = new ManyToOneMatchDTO(
                    bankId: $bId,
                    bookIds: $bookIds,
                    bankAmount: $bank->amount,
                    totalBookAmount: $bank->amount
                );

                unset($remBank[$bId]);
                foreach ($bookIds as $bBookId) {
                    unset($remBook[$bBookId]);
                }
            }
        }

        $unmatchedBank = array_values($remBank);
        $unmatchedBook = array_values($remBook);

        $canonicalInputs = [
            'bank_lines_count' => count($bankLines),
            'book_lines_count' => count($bookLines),
        ];

        $steps = [
            'exact_count' => count($exactMatches),
            'tolerance_count' => count($toleranceMatches),
            'many_to_one_count' => count($manyToOneMatches),
            'unmatched_bank_count' => count($unmatchedBank),
            'unmatched_book_count' => count($unmatchedBook),
        ];

        $results = [
            'exact_matches' => array_map(fn ($m) => ['bank' => $m->bankId, 'book' => $m->bookId], $exactMatches),
            'tolerance_matches' => array_map(fn ($m) => ['bank' => $m->bankId, 'book' => $m->bookId, 'diff' => $m->discrepancyAmount], $toleranceMatches),
            'many_to_one_matches' => array_map(fn ($m) => ['bank' => $m->bankId, 'books' => $m->bookIds], $manyToOneMatches),
            'unmatched_bank_ids' => array_map(fn ($b) => $b->id, $unmatchedBank),
            'unmatched_book_ids' => array_map(fn ($l) => $l->id, $unmatchedBook),
        ];

        $certificate = BankReconciliationCertificate::create($canonicalInputs, $steps, $results);

        return new BankReconciliationResultDTO(
            exactMatches: $exactMatches,
            toleranceMatches: $toleranceMatches,
            manyToOneMatches: $manyToOneMatches,
            unmatchedBankLines: $unmatchedBank,
            unmatchedBookLines: $unmatchedBook,
            certificate: $certificate
        );
    }

    /**
     * Cari himpunan berukuran 2..4 yang jumlahnya persis target.
     * Ukuran terkecil dulu (2, lalu 3, lalu 4), lalu leksikografis terkecil.
     *
     * @param  list<BookLedgerLineDTO>  $candidates
     * @return list<BookLedgerLineDTO>|null
     */
    private function findSubsetSum(array $candidates, int $target): ?array
    {
        $n = count($candidates);

        for ($k = 2; $k <= 4; $k++) {
            if ($k > $n) {
                break;
            }

            $validSubsets = [];
            $this->generateCombinations($candidates, $k, 0, [], $validSubsets, $target);

            if (! empty($validSubsets)) {
                // Urutkan leksikografis berdasarkan daftar id
                usort($validSubsets, function (array $a, array $b) {
                    $count = count($a);
                    for ($i = 0; $i < $count; $i++) {
                        $cmp = strcmp($a[$i]->id, $b[$i]->id);
                        if ($cmp !== 0) {
                            return $cmp;
                        }
                    }

                    return 0;
                });

                return $validSubsets[0];
            }
        }

        return null;
    }

    /**
     * @param  list<BookLedgerLineDTO>  $candidates
     * @param  list<BookLedgerLineDTO>  $current
     * @param  list<list<BookLedgerLineDTO>>  $results
     */
    private function generateCombinations(
        array $candidates,
        int $k,
        int $start,
        array $current,
        array &$results,
        int $target
    ): void {
        if (count($current) === $k) {
            $sum = 0;
            foreach ($current as $item) {
                $sum += $item->amount;
            }
            if ($sum === $target) {
                $results[] = $current;
            }

            return;
        }

        $n = count($candidates);
        for ($i = $start; $i < $n; $i++) {
            $current[] = $candidates[$i];
            $this->generateCombinations($candidates, $k, $i + 1, $current, $results, $target);
            array_pop($current);
        }
    }
}
