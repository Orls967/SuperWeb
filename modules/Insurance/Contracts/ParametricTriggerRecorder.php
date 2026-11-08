<?php

declare(strict_types=1);

namespace Modules\Insurance\Contracts;

/**
 * G7-K29: Kontrak Pencatatan Pemicu Asuransi Parametrik Otomatis.
 */
interface ParametricTriggerRecorder
{
    /**
     * Catat pemicu kejadian parametrik untuk pencairan klaim otomatis.
     *
     * @param  string  $policyNumber  Nomor polis asuransi penumpang
     * @param  string  $flightNumber  Nomor penerbangan
     * @param  string  $triggerType  Tipe pemicu (e.g. DELAY_OVER_180MIN, VOLCANIC_ERUPTION_CLOSURE, CANCELLATION)
     * @param  int  $durationMinutes  Durasi disrupsi dalam menit
     * @param  int  $payoutAmountMinorUnit  Jumlah pencairan santunan dalam minor unit (sen)
     * @param  array<string, mixed>  $telemetryData  Bukti data telemetri atau NOTAM
     * @return string ID referensi pencatatan pemicu klaim
     */
    public function recordTrigger(
        string $policyNumber,
        string $flightNumber,
        string $triggerType,
        int $durationMinutes,
        int $payoutAmountMinorUnit,
        array $telemetryData = []
    ): string;
}
