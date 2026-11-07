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

        $metallurgist = $svc->ensureRole(
            name: 'metallurgist',
            label: 'Ahli Metalurgi & Grade Control',
            description: 'Mengelola assay grade, rekonsiliasi neraca massa tiga titik M5, dan bias lab.'
        );

        $royaltyOfficer = $svc->ensureRole(
            name: 'royalty_officer',
            label: 'Petugas Royalti & Kepatuhan PNBP',
            description: 'Menghitung akrual royalti progresif M4, validasi HBA/HMA, dan pelaporan SIMPONI.'
        );

        $financeOfficer = $svc->ensureRole(
            name: 'finance_officer',
            label: 'Petugas Keuangan Tambang',
            description: 'Menghitung akrual keuangan tambang dan penalti vendor.'
        );

        $hseOfficer = $svc->ensureRole(
            name: 'hse_officer',
            label: 'Petugas K3 Tambang',
            description: 'Mengelola izin kerja berisiko tinggi dan penghentian area darurat.'
        );

        $mineManager = $svc->ensureRole(
            name: 'mine_manager',
            label: 'Kepala Teknik Tambang / Manajer',
            description: 'Otoritas tertinggi operasional konsesi tambang, reklamasi, dan audit dua sumber.'
        );

        $offtakeManager = $svc->ensureRole(
            name: 'offtake_manager',
            label: 'Manajer Penjualan & Offtake',
            description: 'Mengelola kontrak offtake, nominasi kapal, dan saga pengapalan ekspor/domestik.'
        );

        $offtaker = $svc->ensureRole(
            name: 'offtaker',
            label: 'Buyer / Offtaker Komoditas',
            description: 'Portal rekanan pembeli batubara dan mineral konsesi.'
        );

        $minePlanner = $svc->ensureRole(
            name: 'mine_planner',
            label: 'Perencana Tambang',
            description: 'Menyusun rencana tambang cut & fill bulanan, kurva-S, dan alokasi shift.'
        );

        $contractor = $svc->ensureRole(
            name: 'contractor',
            label: 'Kontraktor Tambang',
            description: 'Akses portal rekanan angkut tambang, riwayat armada, dan tagihan.'
        );

        // 2. Permissions
        $permissions = [
            ['name' => 'mining.weighbridge.operate', 'label' => 'Operasikan Jembatan Timbang', 'module' => 'Mining'],
            ['name' => 'mining.dispatch.plan', 'label' => 'Rencanakan & Eksekusi Dispatch Armada', 'module' => 'Mining'],
            ['name' => 'mining.dispatch.override', 'label' => 'Override Penugasan Dispatch', 'module' => 'Mining'],
            ['name' => 'mining.stockpile.manage', 'label' => 'Kelola Stockpile & Blending', 'module' => 'Mining'],
            ['name' => 'mining.reconciliation.execute', 'label' => 'Jalankan Rekonsiliasi Tiga Titik', 'module' => 'Mining'],
            ['name' => 'mining.grade.manage', 'label' => 'Kelola Assay & Neraca Metalurgi', 'module' => 'Mining'],
            ['name' => 'mining.royalty.calculate', 'label' => 'Hitung & Finalkan Royalti PNBP', 'module' => 'Mining'],
            ['name' => 'mining.hse.permit.issue', 'label' => 'Terbitkan Izin Kerja K3 (PTW)', 'module' => 'Mining'],
            ['name' => 'mining.hse.area.stop', 'label' => 'Hentikan / Buka Area Berbahaya', 'module' => 'Mining'],
            ['name' => 'mining.reclamation.manage', 'label' => 'Kelola Dana Jaminan Reklamasi Tambang', 'module' => 'Mining'],
            ['name' => 'mining.offtake.manage', 'label' => 'Kelola Kontrak Offtake & Pengapalan', 'module' => 'Mining'],
            ['name' => 'mining.offtaker.portal', 'label' => 'Akses Portal Nominasi Offtaker', 'module' => 'Mining'],
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

        $svc->grantPermissionsToRole($metallurgist, [
            'mining.grade.manage',
            'mining.reconciliation.execute',
        ]);

        $svc->grantPermissionsToRole($royaltyOfficer, [
            'mining.royalty.calculate',
        ]);

        $svc->grantPermissionsToRole($financeOfficer, [
            'mining.royalty.calculate',
            'mining.reconciliation.execute',
        ]);

        $svc->grantPermissionsToRole($hseOfficer, [
            'mining.hse.permit.issue',
            'mining.hse.area.stop',
        ]);

        $svc->grantPermissionsToRole($offtakeManager, [
            'mining.offtake.manage',
        ]);

        $svc->grantPermissionsToRole($offtaker, [
            'mining.offtaker.portal',
        ]);

        $svc->grantPermissionsToRole($mineManager, array_column($permissions, 'name'));
    }
}
