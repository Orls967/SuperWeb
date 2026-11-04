<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Marine\Application\Services\MarineAquacultureService;

class MarineFisheryT1Seeder extends Seeder
{
    public function run(): void
    {
        $service = app(MarineAquacultureService::class);

        DB::transaction(function () use ($service) {
            $farmers = [];
            for ($i = 1; $i <= 5; $i++) {
                $farmers[] = User::firstOrCreate(
                    ['email' => "farmer{$i}@marine.test"],
                    [
                        'name' => "Pembudidaya {$i}",
                        'password' => bcrypt('password'),
                        'role' => 'farmer',
                        'email_verified_at' => now(),
                    ]
                );
            }

            // 100 kolam
            $ponds = [];
            for ($p = 1; $p <= 100; $p++) {
                $farmer = $farmers[($p - 1) % count($farmers)];
                $code = sprintf('MAR-POND-%03d', $p);
                $ponds[] = $service->createPond(
                    code: $code,
                    name: "Kolam Tambak Vaname #{$p}",
                    farmerId: (int) $farmer->id,
                    capacityUnits: 10000,
                    location: 'Kawasan Marikultur Teluk Semangka'
                );
            }

            // 50 siklus budidaya
            for ($c = 1; $c <= 50; $c++) {
                $pond = $ponds[$c - 1];
                $cycleNumber = sprintf('CYC-2026-%03d', $c);

                $cycle = $service->startCycle(
                    cycleNumber: $cycleNumber,
                    pondId: (int) $pond->id,
                    farmerId: (int) $pond->farmer_id,
                    species: 'SHRIMP_VANNAMEI',
                    stockedCount: 10000,
                    initialWeightGramsPerFish: 2, // 2 gram/ekor = 20 kg awal
                    advanceAmount: 20_000_000 // DP 20jt
                );

                // 2x log pakan total 2.800 kg @ Rp10.000 = Rp28.000.000
                $service->logFeed(
                    cycleId: (int) $cycle->id,
                    feedDate: '2026-03-01',
                    quantityKg: 1400,
                    costPerKg: 10000
                );
                $service->logFeed(
                    cycleId: (int) $cycle->id,
                    feedDate: '2026-04-01',
                    quantityKg: 1400,
                    costPerKg: 10000
                );

                // Panen: 8.500 ekor @ 250 gram = 2.125 kg (Survival 85%)
                // FCR = 2.800 / (2.125 - 20) = 2.800 / 2.105 = 1,330166... -> scaled 1330
                // Grade A: 425 kg @ Rp75.000 = Rp31.875.000
                // Grade B: 1.275 kg @ Rp60.000 = Rp76.500.000
                // Grade C: 425 kg @ Rp40.000 = Rp17.000.000
                // Total Gross = Rp125.375.000
                $harvestNumber = sprintf('HRV-2026-%03d', $c);
                $service->recordHarvest(
                    cycleId: (int) $cycle->id,
                    harvestNumber: $harvestNumber,
                    harvestDate: '2026-05-01',
                    harvestedCount: 8500,
                    avgWeightGrams: 250,
                    gradeAKg: 425,
                    gradeAPrice: 75000,
                    gradeBKg: 1275,
                    gradeBPrice: 60000,
                    gradeCKg: 425,
                    gradeCPrice: 40000
                );

                // Settle Net Payout: Rp125.375.000 - Rp20.000.000 (DP) - Rp28.000.000 (Pakan) = Rp77.375.000
                $idempotencyKey = "SETTLE-T1-{$cycleNumber}";
                $service->settlePayout(
                    cycleId: (int) $cycle->id,
                    idempotencyKey: $idempotencyKey
                );
            }
        });
    }
}
