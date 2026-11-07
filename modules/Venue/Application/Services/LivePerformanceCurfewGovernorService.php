<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;

final class LivePerformanceCurfewGovernorService
{
    public const MUNICIPAL_CURFEW_HOUR = 24; // 00:00 midnight
    public const OVERRUN_PENALTY_PER_15MIN_IDR = 25000000; // Rp 25.000.000 per 15 menit

    /**
     * Evaluate stage performance timeline against municipal noise ordinance curfews.
     *
     * @return array{
     *     curfew_target_time: string,
     *     minutes_until_curfew: int,
     *     warning_state: string,
     *     cut_sound_system_power: bool,
     *     accumulated_penalty_idr: int,
     *     notice: string
     * }
     */
    public function evaluateCurfewCutoff(int $eventDoorsCloseHour = self::MUNICIPAL_CURFEW_HOUR): array
    {
        $now = CarbonImmutable::now();
        $target = $now->startOfDay()->addHours($eventDoorsCloseHour);

        $diffMinutes = (int) $now->diffInMinutes($target, false);

        $warningState = 'NORMAL_RUNNING';
        $cutPower = false;
        $penalty = 0;
        $notice = 'Jadwal penampilan panggung berada dalam batas izin hiburan.';

        if ($diffMinutes < 0) {
            $overrunMinutes = abs($diffMinutes);
            $blocks = (int) ceil($overrunMinutes / 15);
            $penalty = $blocks * self::OVERRUN_PENALTY_PER_15MIN_IDR;
            $warningState = 'OVERRUN_CURFEW_BREACH';
            $cutPower = true;
            $notice = "PELANGGARAN KURVUR: Penampilan melebihi batas waktu {$overrunMinutes} menit. Sistem audio panggung diputuskan.";
        } elseif ($diffMinutes <= 15) {
            $warningState = 'STAGE_CURFEW_WARNING_15MIN';
            $notice = 'Peringatan 15 menit menjelang penutupan panggung. DJ/Artis diwajibkan memainkan encore terakhir.';
        } elseif ($diffMinutes <= 30) {
            $warningState = 'STAGE_CURFEW_WARNING_30MIN';
            $notice = 'Peringatan 30 menit menjelang batas izin hiburan panggung.';
        }

        return [
            'curfew_target_time' => $target->toIso8601String(),
            'minutes_until_curfew' => $diffMinutes,
            'warning_state' => $warningState,
            'cut_sound_system_power' => $cutPower,
            'accumulated_penalty_idr' => $penalty,
            'notice' => $notice,
        ];
    }
}
