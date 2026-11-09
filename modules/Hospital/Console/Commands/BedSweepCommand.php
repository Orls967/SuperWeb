<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\StateMachines\HospitalBedStateMachine;

/**
 * Command Terjadwal: hospital:bed-sweep.
 * Memeriksa status kebersihan ranjang dan sanitasi housekeeping (Fase 87.3).
 */
class BedSweepCommand extends Command
{
    protected $signature = 'hospital:bed-sweep {--auto-clean : Otomatis selesaikan sanitasi ranjang yang melebihi batas waktu}';
    protected $description = 'Memindai status ranjang rawat inap, sanitasi kebersihan, dan audit ketersediaan';

    public function handle(): int
    {
        $this->info("Memulai pemindaian status tempat tidur (hospital:bed-sweep)...");

        $cleaningBeds = HospitalBed::where('status', 'CLEANING')->get();
        $cleanedCount = 0;

        foreach ($cleaningBeds as $bed) {
            // Jika ada flag auto-clean atau sanitasi selesai
            if ($this->option('auto-clean')) {
                HospitalBedStateMachine::transition($bed, HospitalBedStateMachine::STATUS_AVAILABLE, 'Sanitasi otomatis sweep command');
                $cleanedCount++;
            }
        }

        $totalBeds = HospitalBed::count();
        $occupiedBeds = HospitalBed::where('status', 'OCCUPIED')->count();
        $availableBeds = HospitalBed::where('status', 'AVAILABLE')->count();

        $this->table(
            ['Metrik Ranjang', 'Jumlah'],
            [
                ['Total Tempat Tidur', $totalBeds],
                ['Terisi (Occupied)', $occupiedBeds],
                ['Tersedia (Available)', $availableBeds],
                ['Dalam Proses Kebersihan', $cleaningBeds->count()],
                ['Disanitasi Selesai', $cleanedCount],
            ]
        );

        return self::SUCCESS;
    }
}
