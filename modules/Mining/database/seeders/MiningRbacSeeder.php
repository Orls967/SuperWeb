<?php

declare(strict_types=1);

namespace Modules\Mining\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Seeder RBAC khusus modul Mining (KONSEP.md §A6, §12F, Keputusan Pemilik U-5, Z8).
 * Idempoten dan aman dijalankan berulang kali.
 */
class MiningRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. Roles
        $weighbridgeOperator = $svc->ensureRole(
            name: 'weighbridge_operator',
            label: 'Operator Jembatan Timbang',
            description: 'Petugas penimbangan muatan truk dan penerbitan tiket timbang digital.'
        );

        $dispatcherMine = $svc->ensureRole(
            name: 'dispatcher_mine',
            label: 'Dispatcher Tambang',
            description: 'Petugas penugasan armada truk-shovel dan override operasional.'
        );

        $mineEngineer = $svc->ensureRole(
            name: 'mine_engineer',
            label: 'Insinyur Tambang',
            description: 'Mengelola stockpile, rencana blending bijih, dan rekonsiliasi grade.'
        );

        $financeOfficer = $svc->ensureRole(
            name: 'finance_officer',
            label: 'Petugas Keuangan & Royalti',
            description: 'Menghitung akrual royalti bulanan, finalisasi SPT, dan penalti vendor.'
        );

        $hseOfficer = $svc->ensureRole(
            name: 'hse_officer',
            label: 'Petugas K3 Tambang',
            description: 'Mengelola izin kerja berisiko tinggi dan penghentian area darurat.'
        );

        $mineManager = $svc->ensureRole(
            name: 'mine_manager',
            label: 'Kepala Teknik Tambang / Manajer',
            description: 'Otoritas tertinggi operasional konsesi tambang dan audit dua sumber.'
        );

        // 2. Permissions
        $permissions = [
            ['name' => 'mining.weighbridge.operate', 'label' => 'Operasikan Jembatan Timbang', 'module' => 'Mining'],
            ['name' => 'mining.dispatch.plan', 'label' => 'Rencanakan & Eksekusi Dispatch Armada', 'module' => 'Mining'],
            ['name' => 'mining.dispatch.override', 'label' => 'Override Penugasan Dispatch', 'module' => 'Mining'],
            ['name' => 'mining.stockpile.manage', 'label' => 'Kelola Stockpile & Blending', 'module' => 'Mining'],
            ['name' => 'mining.reconciliation.execute', 'label' => 'Jalankan Rekonsiliasi Tiga Titik', 'module' => 'Mining'],
            ['name' => 'mining.royalty.calculate', 'label' => 'Hitung & Finalkan Royalti PNBP', 'module' => 'Mining'],
            ['name' => 'mining.hse.permit.issue', 'label' => 'Terbitkan Izin Kerja K3 (PTW)', 'module' => 'Mining'],
            ['name' => 'mining.hse.area.stop', 'label' => 'Hentikan / Buka Area Berbahaya', 'module' => 'Mining'],
            ['name' => 'mining.audit.view', 'label' => 'Akses Laporan Audit Tambang', 'module' => 'Mining'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], $p['module']);
        }

        // 3. Grant Permissions
        $svc->grantPermissionsToRole($weighbridgeOperator, [
            'mining.weighbridge.operate',
        ]);

        $svc->grantPermissionsToRole($dispatcherMine, [
            'mining.dispatch.plan',
            'mining.dispatch.override',
        ]);

        $svc->grantPermissionsToRole($mineEngineer, [
            'mining.stockpile.manage',
            'mining.reconciliation.execute',
        ]);

        $svc->grantPermissionsToRole($financeOfficer, [
            'mining.royalty.calculate',
            'mining.reconciliation.execute',
        ]);

        $svc->grantPermissionsToRole($hseOfficer, [
            'mining.hse.permit.issue',
            'mining.hse.area.stop',
        ]);

        $svc->grantPermissionsToRole($mineManager, array_column($permissions, 'name'));
    }
}
