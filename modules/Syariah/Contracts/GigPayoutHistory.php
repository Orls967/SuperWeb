<?php

declare(strict_types=1);

namespace Modules\Syariah\Contracts;

/**
 * Kontrak Antar-Modul G7-K6: Gig / Bounty to Islamic Micro-loan.
 *
 * Menyediakan akses baca riwayat pendapatan pekerja lepas/gig,
 * volatilitas penghasilan, dan skor amanah untuk penilaian kelayakan pembiayaan mikro syariah.
 */
interface GigPayoutHistory
{
    /**
     * Mengambil riwayat pendapatan bulanan gig worker dalam sen/minor unit IDR.
     *
     * @param  string  $workerPartyId  Identitas pihak gig worker
     * @param  int  $months  Jumlah bulan histori (default 6 bulan)
     * @return array<int, array{month: string, gross_amount: int, net_payout: int, tasks_completed: int}>
     */
    public function getMonthlyPayoutHistory(string $workerPartyId, int $months = 6): array;

    /**
     * Mengambil skor amanah / reputasi keandalan penyelesaian tugas gig worker (0 - 100).
     *
     * @param  string  $workerPartyId  Identitas pihak gig worker
     */
    public function getAmanahScore(string $workerPartyId): int;
}

