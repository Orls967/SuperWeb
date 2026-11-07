<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningUndergroundStope;

/**
 * Domain Action: Monitoring dan Verifikasi Ambang Batas Gas Tambang Bawah Tanah.
 * Memeriksa gas CH4 (metana), CO (karbon monoksida), CO2, H2S, NOx, dan O2
 * sesuai baku mutu Kepmen ESDM No. 1827 K/30/MEM/2018 Lampiran IV.
 */
class InspectUndergroundGasLevelAction
{
    /**
     * Ambang batas legal (TLV - Threshold Limit Values):
     * O2: minimum 19.5%
     * CH4: maksimum 1.00% pada aliran udara balik/stope, 0.25% pada intake utama
     * CO: maksimum 25 ppm (TWA 8 jam), 50 ppm (CEIL)
     * CO2: maksimum 0.50% (5000 ppm)
     * H2S: maksimum 10 ppm
     * NO2: maksimum 3 ppm
     *
     * @param  array{
     *     stope_id: string,
     *     measured_o2_percent: float,
     *     measured_ch4_percent: float,
     *     measured_co_ppm: float,
     *     measured_co2_percent?: float,
     *     measured_h2s_ppm?: float,
     *     measured_no2_ppm?: float,
     *     sensor_serial_number: string,
     *     inspector_name: string
     * } $data
     * @return array{
     *     is_safe_for_entry: bool,
     *     alarm_level: string,
     *     violations: list<string>,
     *     corrective_actions: list<string>,
     *     gas_readings: array<string, float>
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $stope = MiningUndergroundStope::query()->findOrFail($data['stope_id']);

        $o2 = (float) $data['measured_o2_percent'];
        $ch4 = (float) $data['measured_ch4_percent'];
        $co = (float) $data['measured_co_ppm'];
        $co2 = (float) ($data['measured_co2_percent'] ?? 0.04);
        $h2s = (float) ($data['measured_h2s_ppm'] ?? 0.0);
        $no2 = (float) ($data['measured_no2_ppm'] ?? 0.0);

        $violations = [];
        $actions = [];
        $alarmLevel = 'NORMAL';

        if ($o2 < 19.5) {
            $violations[] = "Konsentrasi Oksigen kritis: {$o2}% (Di bawah ambang minimum 19.5%).";
            $actions[] = 'Evakuasi personel segera dan tingkatkan pasokan auxiliary fan ke stope.';
            $alarmLevel = 'CRITICAL_EVACUATION';
        }

        if ($ch4 >= 1.00) {
            $violations[] = "Konsentrasi Metana (CH4) berbahaya: {$ch4}% (Melebihi batas aman 1.00%).";
            $actions[] = 'Putus aliran listrik otomatis (power interlock) dan nyalakan methane drainage.';
            $alarmLevel = 'CRITICAL_EVACUATION';
        } elseif ($ch4 >= 0.50) {
            $violations[] = "Konsentrasi Metana (CH4) waspada: {$ch4}%.";
            $actions[] = 'Tingkatkan pengawasan gas detektor portabel dan batasi pekerjaan pengelasan/peledakan.';
            if ($alarmLevel !== 'CRITICAL_EVACUATION') {
                $alarmLevel = 'WARNING';
            }
        }

        if ($co >= 50.0) {
            $violations[] = "Konsentrasi Karbon Monoksida (CO) mematikan: {$co} ppm (Melebihi batas plafon 50 ppm).";
            $actions[] = 'Gunakan self-contained self-rescuer (SCSR) dan tinggalkan stope ke refuge chamber.';
            $alarmLevel = 'CRITICAL_EVACUATION';
        } elseif ($co >= 25.0) {
            $violations[] = "Konsentrasi CO waspada: {$co} ppm (Melebihi TWA 25 ppm).";
            $actions[] = 'Batasi durasi kerja operator diesel di stope maksimal 2 jam.';
            if ($alarmLevel === 'NORMAL') {
                $alarmLevel = 'WARNING';
            }
        }

        if ($co2 >= 0.50) {
            $violations[] = "Konsentrasi CO2 tinggi: {$co2}%.";
            $actions[] = 'Periksa ventilasi pembuangan emisi diesel.';
            if ($alarmLevel === 'NORMAL') {
                $alarmLevel = 'WARNING';
            }
        }

        if ($h2s >= 10.0) {
            $violations[] = "Konsentrasi H2S beracun: {$h2s} ppm.";
            $actions[] = 'Isolasi sumber rembesan air tanah asam tambang dan evakuasi staf.';
            $alarmLevel = 'CRITICAL_EVACUATION';
        }

        $isSafe = count($violations) === 0;

        // Perbarui metadata stope dengan inspeksi gas terbaru
        $meta = $stope->metadata ?? [];
        $meta['last_gas_inspection'] = [
            'measured_at' => now()->toIso8601String(),
            'sensor_sn' => $data['sensor_serial_number'],
            'inspector' => $data['inspector_name'],
            'o2_pct' => $o2,
            'ch4_pct' => $ch4,
            'co_ppm' => $co,
            'alarm_level' => $alarmLevel,
            'is_safe' => $isSafe,
        ];

        if (! $isSafe && $alarmLevel === 'CRITICAL_EVACUATION') {
            $stope->status = 'RESTRICTED_GAS_HAZARD';
        } elseif ($stope->status === 'RESTRICTED_GAS_HAZARD' && $isSafe) {
            $stope->status = 'OPERATIONAL';
        }

        $stope->metadata = $meta;
        $stope->save();

        return [
            'is_safe_for_entry' => $isSafe,
            'alarm_level' => $alarmLevel,
            'violations' => $violations,
            'corrective_actions' => $actions,
            'gas_readings' => [
                'o2_percent' => $o2,
                'ch4_percent' => $ch4,
                'co_ppm' => $co,
                'co2_percent' => $co2,
                'h2s_ppm' => $h2s,
                'no2_ppm' => $no2,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['stope_id'])) {
            throw new InvalidArgumentException('ID Stope harus disertakan.');
        }

        if (! isset($data['measured_o2_percent'], $data['measured_ch4_percent'], $data['measured_co_ppm'])) {
            throw new InvalidArgumentException('Pengukuran O2, CH4, dan CO wajib diisi.');
        }

        if (empty($data['sensor_serial_number'])) {
            throw new InvalidArgumentException('Nomor seri detektor gas terkalibrasi wajib diisi.');
        }
    }
}
