<?php

declare(strict_types=1);

namespace Modules\Finance\Contracts;

/**
 * Kontrak baca eksposur pinjaman ritel/korporat untuk pemantauan covenant fasilitas kredit.
 */
interface LoanExposureProvider
{
    /**
     * Dapatkan total eksposur pinjaman aktif dalam IDR minor unit (rupiah) pada tanggal tertentu.
     */
    public function totalLoanExposureMinor(string $asOfDate): int;

    /**
     * Dapatkan total beban bunga pinjaman periode berjalan dalam IDR minor unit.
     */
    public function totalInterestExpenseMinor(string $period): int;
}
