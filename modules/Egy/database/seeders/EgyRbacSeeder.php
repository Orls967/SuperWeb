<?php

declare(strict_types=1);

namespace Modules\Egy\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Idempotently seeds RBAC roles and permissions for Lini 13 (Energi & Utilitas).
 */
class EgyRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. ROLES
        $roles = [
            [
                'name' => 'grid_operator',
                'label' => 'Operator Grid & Microgrid',
                'description' => 'Operasi grid, smart meter telemetry, pemadaman pulau microgrid.',
            ],
            [
                'name' => 'energy_manager',
                'label' => 'Manajer Energi & Utilitas',
                'description' => 'Monitoring konsumsi properti, kalkulasi tagihan TOU, manajemen invoice.',
            ],
            [
                'name' => 'genco_trader',
                'label' => 'Genco Energy Trader',
                'description' => 'Perdagangan energi, unit commitment pembangkit, PPA antar-entitas, arbitrase baterai.',
            ],
            [
                'name' => 'energy_auditor',
                'label' => 'Auditor Energi',
                'description' => 'Audit register meter, verifikasi sertifikat numerik, rekonsiliasi tagihan.',
            ],
            [
                'name' => 'renewable_dev',
                'label' => 'Pengembang Energi Terbarukan',
                'description' => 'Monitoring REC, aset baterai storage, perencanaan surya microgrid.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $roleModels[$r['name']] = $svc->ensureRole($r['name'], $r['label'], $r['description']);
        }

        // 2. PERMISSIONS
        $permissions = [
            ['name' => 'egy.view', 'label' => 'Lihat Portal Energi'],
            ['name' => 'egy.meter.ingest', 'label' => 'Ingest Telemetri Meter'],
            ['name' => 'egy.meter.read', 'label' => 'Baca Riwayat Meter'],
            ['name' => 'egy.grid.dispatch', 'label' => 'Dispatch Beban Grid'],
            ['name' => 'egy.microgrid.island', 'label' => 'Eksekusi Pulau Microgrid'],
            ['name' => 'egy.billing.calculate', 'label' => 'Kalkulasi Tagihan TOU'],
            ['name' => 'egy.invoice.manage', 'label' => 'Kelola Faktur Utilitas Konsolidasi'],
            ['name' => 'egy.ppa.settle', 'label' => 'Penyelesaian PPA Antar-Entitas'],
            ['name' => 'egy.unit_commitment.plan', 'label' => 'Perencanaan Unit Commitment'],
            ['name' => 'egy.battery.arbitrage', 'label' => 'Optimasi Arbitrase Baterai BESS'],
            ['name' => 'egy.battery.plan', 'label' => 'Perencanaan Kapasitas Baterai'],
            ['name' => 'egy.rec.manage', 'label' => 'Penerbitan & Perdagangan REC'],
            ['name' => 'egy.audit.read', 'label' => 'Baca Audit Trail Energi'],
            ['name' => 'egy.audit.verify', 'label' => 'Verifikasi Sertifikat Numerik'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], 'egy');
        }

        // 3. ASSIGN PERMISSIONS TO ROLES
        $svc->grantPermissionsToRole($roleModels['grid_operator'], [
            'egy.view', 'egy.meter.ingest', 'egy.meter.read', 'egy.grid.dispatch', 'egy.microgrid.island',
        ]);

        $svc->grantPermissionsToRole($roleModels['energy_manager'], [
            'egy.view', 'egy.meter.read', 'egy.billing.calculate', 'egy.invoice.manage',
        ]);

        $svc->grantPermissionsToRole($roleModels['genco_trader'], [
            'egy.view', 'egy.ppa.settle', 'egy.unit_commitment.plan', 'egy.battery.arbitrage',
        ]);

        $svc->grantPermissionsToRole($roleModels['energy_auditor'], [
            'egy.view', 'egy.meter.read', 'egy.audit.read', 'egy.audit.verify',
        ]);

        $svc->grantPermissionsToRole($roleModels['renewable_dev'], [
            'egy.view', 'egy.battery.plan', 'egy.rec.manage',
        ]);

        // Admin gets all
        $adminRole = $svc->ensureRole('admin', 'Super Admin', 'Akses penuh ke seluruh platform.');
        $svc->grantPermissionsToRole($adminRole, array_column($permissions, 'name'));
    }
}
