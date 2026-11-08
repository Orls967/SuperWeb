<?php

declare(strict_types=1);

namespace Modules\Port\Contracts;

use Carbon\CarbonImmutable;

/**
 * Kontrak G7-K27: Penyedia Jendela Waktu Panggilan Kapal & Dermaga (Vessel Call Window Provider).
 * 
 * Digunakan oleh modul maritim, logistik kargo, dan sistem charter untuk mengalokasikan
 * jendela waktu tambat kapal (berth window), pandu tunda, dan verifikasi ketersediaan dermaga.
 */
interface VesselCallWindowProvider
{
    /**
     * Cari jendela waktu tambat yang tersedia untuk kapal dengan spesifikasi LOA dan draft tertentu.
     *
     * @return array{
     *     berth_code: string,
     *     window_start: string,
     *     window_end: string,
     *     max_draft_m: float,
     *     allocated_length_m: float,
     *     is_available: bool
     * }|null
     */
    public function findAvailableBerthWindow(
        string $portCode,
        CarbonImmutable $estimatedArrival,
        CarbonImmutable $estimatedDeparture,
        float $lengthOverallM,
        float $arrivalDraftM
    ): ?array;

    /**
     * Kunci / cadangkan jendela waktu panggilan kapal.
     *
     * @return array{
     *     booking_id: int,
     *     port_call_id: int,
     *     berth_code: string,
     *     booking_status: string,
     *     window_start: string,
     *     window_end: string
     * }
     */
    public function reserveCallWindow(
        int $portCallId,
        string $berthCode,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        float $allocatedLengthM,
        float $maxDraftM
    ): array;

    /**
     * Batalkan atau lepaskan reservasi jendela panggilan kapal (kompensasi saga).
     */
    public function releaseCallWindow(int $bookingId, string $cancellationReason): bool;
}

