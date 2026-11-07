<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Kalkulator Demurrage & Despatch Pengapalan Tongkang / Vessel Tambang (D94-7).
 *
 * Mengkalkulasi kompensasi denda keterlambatan (Demurrage) atau insentif percepatan
 * muat (Despatch / Dispatch) berdasarkan kontrak charter party:
 * - Waktu diizinkan (Laytime allowed) = Volume (ton) / Loading Rate (ton/hari)
 * - Waktu aktual pemuatan dikurangi cuaca buruk (Weather Working Days - WWD SHINC/FHEX)
 * - Nilai Demurrage per hari (USD/hari) dan Despatch (umumnya 50% dari Demurrage).
 */
final class MineDemurrageDespatchCalculator
{
    /**
     * @param array{
     *     contracted_volume_tons: int,
     *     loading_rate_tons_per_day: int,
     *     demurrage_rate_usd_per_day: int,
     *     despatch_rate_usd_per_day?: int,
     *     laycan_start: string,
     *     notice_of_readiness_tendered: string,
     *     loading_commenced: string,
     *     loading_completed: string,
     *     weather_downtime_hours?: int
     * } $data
     * @return array{
     *     allowed_laytime_hours: float,
     *     actual_used_laytime_hours: float,
     *     weather_deducted_hours: int,
     *     net_laytime_hours: float,
     *     is_demurrage: bool,
     *     is_despatch: bool,
     *     settlement_amount_usd_cents: int,
     *     status: string,
     *     explanation: string
     * }
     */
    public function calculate(array $data): array
    {
        $volumeTons = (int) $data['contracted_volume_tons'];
        $loadingRate = (int) $data['loading_rate_tons_per_day'];
        $demurrageRateUsd = (int) $data['demurrage_rate_usd_per_day'];
        $despatchRateUsd = (int) ($data['despatch_rate_usd_per_day'] ?? intdiv($demurrageRateUsd, 2));

        if ($volumeTons <= 0 || $loadingRate <= 0 || $demurrageRateUsd <= 0) {
            throw new InvalidArgumentException('Parameter volume, laju muat, dan tarif demurrage harus bernilai positif.');
        }

        $norTendered = CarbonImmutable::parse($data['notice_of_readiness_tendered']);
        $loadingCommenced = CarbonImmutable::parse($data['loading_commenced']);
        $loadingCompleted = CarbonImmutable::parse($data['loading_completed']);
        $weatherDowntimeHours = (int) ($data['weather_downtime_hours'] ?? 0);

        if ($loadingCompleted->lessThan($loadingCommenced)) {
            throw new InvalidArgumentException('Waktu selesai pemuatan tidak boleh mendahului waktu mulai.');
        }

        // 1. Hitung Waktu Diizinkan (Allowed Laytime) dalam jam
        $allowedDays = $volumeTons / $loadingRate;
        $allowedLaytimeHours = round($allowedDays * 24, 2);

        // 2. Hitung Waktu Faktur Penggunaan (Actual Used Laytime)
        $grossUsedHours = $loadingCommenced->diffInMinutes($loadingCompleted) / 60;

        // Kurangi downtime akibat hujan deras/gelombang tinggi (WWD clause)
        $netUsedHours = max(0.0, round($grossUsedHours - $weatherDowntimeHours, 2));

        // 3. Selisih waktu: positif = terlambat (demurrage), negatif = lebih cepat (despatch)
        $diffHours = $netUsedHours - $allowedLaytimeHours;

        $isDemurrage = ($diffHours > 0.05);
        $isDespatch = ($diffHours < -0.05);

        $settlementCents = 0;
        $status = 'ON_SCHEDULE';
        $explanation = 'Pemuatan kargo selesai tepat waktu dalam batas laytime.';

        if ($isDemurrage) {
            $demurrageDays = $diffHours / 24;
            $settlementCents = (int) round($demurrageDays * $demurrageRateUsd * 100);
            $status = 'DEMURRAGE_PAYABLE';
            $explanation = sprintf(
                'Keterlambatan pemuatan %.2f jam melebihi laytime diizinkan (%.2f jam). Demurrage terutang: $%.2f.',
                $diffHours,
                $allowedLaytimeHours,
                $settlementCents / 100
            );
        } elseif ($isDespatch) {
            $savedHours = abs($diffHours);
            $savedDays = $savedHours / 24;
            $settlementCents = (int) round($savedDays * $despatchRateUsd * 100);
            $status = 'DESPATCH_EARNED';
            $explanation = sprintf(
                'Pemuatan dipercepat %.2f jam dari laytime diizinkan. Insentif despatch diperoleh: $%.2f.',
                $savedHours,
                $settlementCents / 100
            );
        }

        return [
            'allowed_laytime_hours' => $allowedLaytimeHours,
            'actual_used_laytime_hours' => round($grossUsedHours, 2),
            'weather_deducted_hours' => $weatherDowntimeHours,
            'net_laytime_hours' => $netUsedHours,
            'is_demurrage' => $isDemurrage,
            'is_despatch' => $isDespatch,
            'settlement_amount_usd_cents' => $settlementCents,
            'status' => $status,
            'explanation' => $explanation,
        ];
    }
}
