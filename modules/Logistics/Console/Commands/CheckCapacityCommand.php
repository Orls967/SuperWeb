<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Brick\Math\BigDecimal;
use Illuminate\Console\Command;
use Modules\Logistics\Domain\Models\CapacityReservation;
use Modules\Logistics\Domain\Models\Schedule;

class CheckCapacityCommand extends Command
{
    protected $signature = 'lgx:capacity-check {--schedule_id= : Audit ID jadwal tertentu saja}';

    protected $description = 'Audit dan verifikasi integritas pemakaian kapasitas seluruh jadwal operasi jaringan logistik';

    public function handle(): int
    {
        $this->info('Memulai audit integritas pemakaian kapasitas jadwal operasi...');

        $query = Schedule::query();
        if ($this->option('schedule_id')) {
            $query->where('id', (int) $this->option('schedule_id'));
        }

        $schedules = $query->get();
        if ($schedules->isEmpty()) {
            $this->warn('Tidak ada data jadwal untuk diaudit.');

            return self::SUCCESS;
        }

        $discrepancies = [];

        foreach ($schedules as $schedule) {
            $activeReservations = CapacityReservation::where('schedule_id', $schedule->id)
                ->where('status', 'active')
                ->get();

            // Calculate expected sums
            $expectedWeight = BigDecimal::of('0.000');
            $expectedVolume = 0;
            $expectedTeu = 0;
            $expectedUld = 0;

            foreach ($activeReservations as $res) {
                $expectedWeight = $expectedWeight->plus(BigDecimal::of((string) ($res->allocated_weight_kg ?: '0.000')));
                $expectedVolume += (int) $res->allocated_volume_dm3;
                $expectedTeu += (int) $res->allocated_teu;
                $expectedUld += (int) $res->allocated_uld_positions;
            }

            $currentUsedWeight = BigDecimal::of((string) ($schedule->used_weight_kg ?: '0.000'));
            $currentCapWeight = BigDecimal::of((string) ($schedule->cap_weight_kg ?: '0.000'));

            // 1. Check if used counters exceed capacity limit
            if ($currentUsedWeight->isGreaterThan($currentCapWeight)) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "Berat terpakai ({$currentUsedWeight} kg) > Kapasitas ({$currentCapWeight} kg)",
                ];
            }
            if ($schedule->used_volume_dm3 > $schedule->cap_volume_dm3) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "Volume terpakai ({$schedule->used_volume_dm3} dm³) > Kapasitas ({$schedule->cap_volume_dm3} dm³)",
                ];
            }
            if ($schedule->cap_teu > 0 && $schedule->used_teu > $schedule->cap_teu) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "TEU terpakai ({$schedule->used_teu}) > Kapasitas ({$schedule->cap_teu})",
                ];
            }
            if ($schedule->cap_uld_positions > 0 && $schedule->used_uld_positions > $schedule->cap_uld_positions) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "Posisi ULD terpakai ({$schedule->used_uld_positions}) > Kapasitas ({$schedule->cap_uld_positions})",
                ];
            }

            // 2. Check if used counters strictly equal sum of active allocations
            if ($currentUsedWeight->compareTo($expectedWeight) !== 0) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "Selisih berat: Penghitung ({$currentUsedWeight} kg) != Total Alokasi Aktif ({$expectedWeight} kg)",
                ];
            }
            if ($schedule->used_volume_dm3 !== $expectedVolume) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "Selisih volume: Penghitung ({$schedule->used_volume_dm3} dm³) != Total Alokasi Aktif ({$expectedVolume} dm³)",
                ];
            }
            if ($schedule->used_teu !== $expectedTeu) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "Selisih TEU: Penghitung ({$schedule->used_teu}) != Total Alokasi Aktif ({$expectedTeu})",
                ];
            }
            if ($schedule->used_uld_positions !== $expectedUld) {
                $discrepancies[] = [
                    'schedule' => $schedule->schedule_number,
                    'issue' => "Selisih ULD: Penghitung ({$schedule->used_uld_positions}) != Total Alokasi Aktif ({$expectedUld})",
                ];
            }
        }

        if (! empty($discrepancies)) {
            $this->error('❌ Terdeteksi '.count($discrepancies).' inkonsistensi kapasitas!');
            $this->table(['Jadwal Operasi', 'Keterangan Diskrepansi'], $discrepancies);

            return self::FAILURE;
        }

        $this->info('✓ Seluruh '.$schedules->count().' jadwal terverifikasi: penghitung terpakai cocok sempurna dengan alokasi aktif dan tidak pernah melebihi kapasitas.');

        return self::SUCCESS;
    }
}
