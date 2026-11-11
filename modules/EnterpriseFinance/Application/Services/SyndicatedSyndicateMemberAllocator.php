<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Pengalokasi Porsi Partisipasi Bank Anggota Sindikasi (54.7).
 * Mengelola proporsi komitmen pendanaan kredit sindikasi multi-bank:
 * - Lead Arranger / Facility Agent Bank
 * - Mandated Lead Arranger (MLA)
 * - Participating Syndicate Member Banks
 * Menjamin pembagian hak penerimaan bunga dan pokok cicilan berimbang tepat 1000‰ (100%).
 */
class SyndicatedSyndicateMemberAllocator
{
    /**
     * Membagi total penarikan kas atau angsuran ke masing-masing bank peserta sindikasi.
     *
     * @param  list<array{bank_code: string, bank_name: string, role: string, participation_permille: int}>  $members
     * @return array{
     *     total_amount_minor: int,
     *     is_zero_sum_balanced: bool,
     *     allocations: list<array{bank_code: string, role: string, permille: int, share_minor: int}>
     * }
     */
    public function allocateToMembers(int $totalAmountMinor, array $members): array
    {
        if ($totalAmountMinor <= 0) {
            throw new InvalidArgumentException('Total nominal alokasi sindikasi harus bernilai positif.');
        }

        $totalPermille = 0;
        foreach ($members as $m) {
            $totalPermille += (int) $m['participation_permille'];
        }

        if ($totalPermille !== 1000) {
            throw new InvalidArgumentException("Total kuota partisipasi sindikasi ({$totalPermille}‰) harus tepat 1000‰ (100%).");
        }

        $allocatedSum = 0;
        $allocations = [];
        $memberCount = count($members);

        foreach ($members as $idx => $m) {
            $permille = (int) $m['participation_permille'];

            // Elemen terakhir menyerap selisih pembulatan (Zero-Sum Invariant)
            if ($idx === $memberCount - 1) {
                $share = $totalAmountMinor - $allocatedSum;
            } else {
                $share = (int) intdiv($totalAmountMinor * $permille, 1000);
            }

            $allocatedSum += $share;

            $allocations[] = [
                'bank_code' => $m['bank_code'],
                'role' => $m['role'],
                'permille' => $permille,
                'share_minor' => $share,
            ];
        }

        return [
            'total_amount_minor' => $totalAmountMinor,
            'is_zero_sum_balanced' => ($allocatedSum === $totalAmountMinor),
            'allocations' => $allocations,
        ];
    }
}
