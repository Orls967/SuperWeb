<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Mall\Domain\Models\ParkingMember;
use Modules\Mall\Domain\Models\ParkingTariff;

class ParkingTariffCalculator
{
    /**
     * Hitung tarif parkir progresif dengan pembulatan jam, batas harian, dan denda tiket hilang.
     *
     * @return array{
     *     duration_minutes: int,
     *     billed_hours: int,
     *     base_fee: int,
     *     penalty_fee: int,
     *     discount_amount: int,
     *     total_fee: int,
     *     is_free: bool
     * }
     */
    public function calculate(
        ParkingTariff $tariff,
        int $durationMinutes,
        bool $isLostTicket = false,
        ?ParkingMember $member = null,
        int $discountAmount = 0
    ): array {
        // 1. Member aktif bebas biaya parkir reguler
        if ($member && $member->isValid() && ! $isLostTicket) {
            return [
                'duration_minutes' => $durationMinutes,
                'billed_hours' => $this->billedHours($durationMinutes),
                'base_fee' => 0,
                'penalty_fee' => 0,
                'discount_amount' => 0,
                'total_fee' => 0,
                'is_free' => true,
            ];
        }

        // 2. Cek masa tenggang (Grace Period, misal 15 menit pertama)
        if ($durationMinutes <= $tariff->grace_period_minutes && ! $isLostTicket) {
            return [
                'duration_minutes' => $durationMinutes,
                'billed_hours' => 0,
                'base_fee' => 0,
                'penalty_fee' => 0,
                'discount_amount' => 0,
                'total_fee' => 0,
                'is_free' => true,
            ];
        }

        $billedHours = $this->billedHours($durationMinutes);
        $baseFee = $this->feeForHours($tariff, $billedHours);

        // 3. Denda tiket hilang
        $penaltyFee = $isLostTicket ? (int) $tariff->lost_ticket_penalty : 0;

        // 4. Potongan diskon validasi tenant tidak boleh melebihi tagihan
        $rawTotal = $baseFee + $penaltyFee;
        $discountAmount = max(0, min($discountAmount, $rawTotal));
        $totalFee = $rawTotal - $discountAmount;

        return [
            'duration_minutes' => $durationMinutes,
            'billed_hours' => $billedHours,
            'base_fee' => $baseFee,
            'penalty_fee' => $penaltyFee,
            'discount_amount' => $discountAmount,
            'total_fee' => $totalFee,
            'is_free' => $totalFee === 0,
        ];
    }

    /**
     * Nilai rupiah dari N jam pertama yang ditanggung tenant lewat validasi belanja.
     * Potongan dibatasi maksimal sebesar tarif dasar yang seharusnya dibayar.
     */
    public function discountForFreeHours(ParkingTariff $tariff, int $durationMinutes, int $freeHours): int
    {
        if ($freeHours <= 0) {
            return 0;
        }

        $billedHours = $this->billedHours($durationMinutes);

        if ($billedHours <= 0) {
            return 0;
        }

        $coveredHours = min($freeHours, $billedHours);

        return min(
            $this->feeForHours($tariff, $coveredHours),
            $this->feeForHours($tariff, $billedHours)
        );
    }

    /**
     * Pembulatan durasi ke jam penuh berikutnya (61 menit -> 2 jam).
     */
    public function billedHours(int $durationMinutes): int
    {
        return intdiv(max(1, $durationMinutes) + 59, 60);
    }

    /**
     * Tarif progresif: jam pertama, jam berikutnya, dengan batas maksimal per siklus 24 jam.
     */
    private function feeForHours(ParkingTariff $tariff, int $billedHours): int
    {
        if ($billedHours <= 0) {
            return 0;
        }

        $fullDays = intdiv($billedHours, 24);
        $remainderHours = $billedHours % 24;

        $maxDailyBd = BigDecimal::of($tariff->max_daily_rate);
        $firstHourBd = BigDecimal::of($tariff->first_hour_rate);
        $subsequentBd = BigDecimal::of($tariff->subsequent_hour_rate);

        $remainderCostBd = BigDecimal::zero();
        if ($remainderHours > 0) {
            $remainderCostBd = $firstHourBd;
            if ($remainderHours > 1) {
                $remainderCostBd = $remainderCostBd->plus($subsequentBd->multipliedBy($remainderHours - 1));
            }
            // Batasi tarif jam sisa agar tidak melebihi batas harian
            if ($remainderCostBd->isGreaterThan($maxDailyBd)) {
                $remainderCostBd = $maxDailyBd;
            }
        }

        return $maxDailyBd->multipliedBy($fullDays)
            ->plus($remainderCostBd)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();
    }
}
