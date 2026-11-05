<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Forest\Domain\Models\ForestConcessionPlot;
use Modules\Forest\Domain\Models\ForestHarvestPermit;
use Modules\Forest\Domain\Models\ForestTimberLog;
use Modules\Forest\Domain\Models\ForestTreeInventory;

final class ForestT1Seeder extends Seeder
{
    public function run(): void
    {
        // 200 petak hutan
        $plotRows = [];
        for ($i = 1; $i <= 200; $i++) {
            $plotRows[] = [
                'plot_code' => sprintf('PLT-T1-%04d', $i),
                'concession_name' => 'Concession Unit '.($i % 10 + 1),
                'area_hectares' => 50 + ($i % 50),
                'annual_quota_m3' => 1000 + ($i * 10),
                'harvested_volume_m3' => 0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ForestConcessionPlot::insert($plotRows);

        $plotIds = ForestConcessionPlot::pluck('id')->toArray();

        // 10.000 pohon dalam inventaris
        $treeRows = [];
        for ($t = 1; $t <= 10000; $t++) {
            $plotId = $plotIds[$t % count($plotIds)];
            $treeRows[] = [
                'tree_tag' => sprintf('TREE-T1-%06d', $t),
                'plot_id' => $plotId,
                'species_name' => ($t % 2 === 0) ? 'Shorea leprosula (Meranti)' : 'Dipterocarpus (Keruing)',
                'dbh_cm' => 40 + ($t % 40),
                'height_m' => 15 + ($t % 15),
                'form_factor_pct' => 70,
                'calculated_volume_dm3' => 1500 + ($t % 500),
                'status' => 'standing',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($treeRows) >= 2000) {
                ForestTreeInventory::insert($treeRows);
                $treeRows = [];
            }
        }
        if (count($treeRows) > 0) {
            ForestTreeInventory::insert($treeRows);
        }

        // Buat sample permit dan log kayu terverifikasi
        $permit = ForestHarvestPermit::create([
            'permit_code' => 'PRM-T1-SAMPLE-001',
            'plot_id' => $plotIds[0],
            'authorized_volume_m3' => 500,
            'harvested_volume_m3' => 10,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => 'approved',
            'requester_user_ref' => 10,
            'approver_user_ref' => 20,
            'approved_at' => now(),
        ]);

        $prevHash = str_repeat('0', 64);
        for ($s = 1; $s <= 5; $s++) {
            $logCode = sprintf('LOG-T1-SAMPLE-%03d', $s);
            $volDm3 = 2000;
            $canonicalData = "{$logCode}:{$permit->permit_code}:{$s}:{$volDm3}:{$prevHash}";
            $canonicalHash = hash('sha256', $canonicalData);

            ForestTimberLog::create([
                'log_code' => $logCode,
                'plot_id' => $permit->plot_id,
                'permit_id' => $permit->id,
                'tree_tag' => "TREE-T1-SAMPLE-{$s}",
                'volume_dm3' => $volDm3,
                'serial_number' => $s,
                'prev_hash' => $prevHash,
                'canonical_hash' => $canonicalHash,
                'stage' => 'stump',
            ]);

            $prevHash = $canonicalHash;
        }
    }
}
