<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

class AiRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // Ensure roles
        $riskOfficer = $svc->ensureRole(
            'risk_officer',
            'Risk Officer',
            'Petugas kepatuhan dan analisis anomali transaksi real-time (Fase 64).'
        );

        $riskManager = $svc->ensureRole(
            'risk_manager',
            'Risk Manager',
            'Manajer risiko berwenang melepaskan karantina transaksi (four-eyes).'
        );

        $planner = $svc->ensureRole(
            'planner',
            'Supply Planner',
            'Perencana persediaan dan pengadaan preskriptif adaptif.'
        );

        $plannerManager = $svc->ensureRole(
            'planner_manager',
            'Supply Planning Manager',
            'Manajer perencanaan persediaan berwenang menyetujui PO otonom di atas plafon.'
        );

        // Ensure permissions
        $permissions = [
            ['name' => 'ai.anomalies.view', 'label' => 'Lihat Anomali Transaksi'],
            ['name' => 'ai.anomalies.screen', 'label' => 'Screen Anomali Transaksi'],
            ['name' => 'ai.quarantine.view', 'label' => 'Lihat Karantina Settlement'],
            ['name' => 'ai.quarantine.release', 'label' => 'Rilis Karantina Transaksi (Four-Eyes)'],
            ['name' => 'ai.stock.view', 'label' => 'Lihat Rekomendasi Stok'],
            ['name' => 'ai.stock.generate', 'label' => 'Generate Rekomendasi Stok'],
            ['name' => 'ai.stock.approve', 'label' => 'Approval PO Rekomendasi Stok'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], 'core_ai');
        }

        // Grant permissions
        $svc->grantPermissionsToRole($riskOfficer, [
            'ai.anomalies.view',
            'ai.anomalies.screen',
            'ai.quarantine.view',
        ]);

        $svc->grantPermissionsToRole($riskManager, [
            'ai.anomalies.view',
            'ai.quarantine.view',
            'ai.quarantine.release',
        ]);

        $svc->grantPermissionsToRole($planner, [
            'ai.stock.view',
            'ai.stock.generate',
        ]);

        $svc->grantPermissionsToRole($plannerManager, [
            'ai.stock.view',
            'ai.stock.generate',
            'ai.stock.approve',
        ]);
    }
}

