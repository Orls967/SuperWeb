<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\VerifyPassportAction;
use Modules\Core\Domain\Models\Vehicle;

class VerifyPassportsCommand extends Command
{
    protected $signature = 'core:verify-passports {--vehicle= : UUID kendaraan spesifik}';

    protected $description = 'Verifikasi keabsahan kriptografis rantai hash-chain seluruh Paspor Kendaraan';

    public function handle(VerifyPassportAction $verifyAction): int
    {
        $this->info('Memulai verifikasi integritas rantai Paspor Kendaraan...');

        $query = Vehicle::query();
        if ($uuid = $this->option('vehicle')) {
            $query->where('uuid', $uuid);
        }

        $vehicles = $query->get();
        $this->line("Memeriksa {$vehicles->count()} kendaraan...");

        $allValid = true;

        foreach ($vehicles as $vehicle) {
            $result = $verifyAction->execute($vehicle);
            $carName = $vehicle->car ? "{$vehicle->car->brand->name} {$vehicle->car->model}" : ($vehicle->plate_number ?: 'Kendaraan #'.$vehicle->id);

            if ($result['is_valid']) {
                $this->line("  [✓] {$carName} ({$vehicle->uuid}): {$result['event_count']} event terverifikasi.");
            } else {
                $allValid = false;
                $this->error("  [✗] {$carName} ({$vehicle->uuid}): RUSAK pada sequence {$result['broken_at_sequence']}! {$result['message']}");
            }
        }

        if ($allValid) {
            $this->info('✓ Seluruh paspor kendaraan valid dan tidak ada manipulasi data.');

            return self::SUCCESS;
        }

        $this->error('✗ Ditemukan ketidakcocokan integritas pada satu atau lebih paspor kendaraan!');

        return self::FAILURE;
    }
}
