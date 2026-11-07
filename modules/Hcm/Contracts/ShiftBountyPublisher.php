<?php

declare(strict_types=1);

namespace Modules\Hcm\Contracts;

use Carbon\Carbon;

/**
 * G7-K18 Shift Bounty Publisher Contract.
 *
 * Digunakan oleh modul Venue (beach club, festival malam) untuk mempublikasikan
 * insentif lembur kru bartender, teknisi panggung, dan petugas keamanan turnstile.
 */
interface ShiftBountyPublisher
{
    /**
     * Mempublikasikan shift bounty lembur/acara.
     *
     * @param  string  $costCenter  Kode pusat biaya (mis. 'CC-VEN-ATLAS')
     * @param  string  $title  Judul tugas lembur
     * @param  string  $description  Rincian tanggung jawab
     * @param  Carbon  $shiftStart  Waktu mulai shift
     * @param  Carbon  $shiftEnd  Waktu selesai shift
     * @param  int  $hourlyRateIdr  Tarif per jam dalam Rupiah penuh
     * @param  string|null  $requiredCertification  Sertifikasi yang diwajibkan (mis. 'BAR_MIXOLOGY_LEVEL2')
     * @param  bool  $requiresHighRiskPermit  Izin kerja risiko tinggi
     * @param  int  $speedBonusIdr  Bonus respons cepat
     * @return array{
     *     bounty_id: int|string,
     *     bounty_code: string,
     *     insurance_ref: string,
     *     cost_center: string,
     *     hourly_rate_idr: int,
     *     status: string
     * }
     */
    public function publishBounty(
        string $costCenter,
        string $title,
        string $description,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        int $hourlyRateIdr,
        ?string $requiredCertification = null,
        bool $requiresHighRiskPermit = false,
        int $speedBonusIdr = 0
    ): array;
}
