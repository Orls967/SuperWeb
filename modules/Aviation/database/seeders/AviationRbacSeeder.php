<?php

declare(strict_types=1);

namespace Modules\Aviation\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Seeder RBAC khusus modul Aviation (KONSEP.md §A6, Fase 177).
 * Idempoten dan aman dijalankan berulang kali.
 */
class AviationRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. Roles
        $opsController = $svc->ensureRole(
            name: 'ops_controller',
            label: 'Network Ops Controller',
            description: 'Mengelola jadwal penerbangan operasional, status armada, dan kontrol rute.'
        );

        $stationManager = $svc->ensureRole(
            name: 'station_manager',
            label: 'Airport Station Manager',
            description: 'Mengelola koordinasi slot musiman bandara dan alokasi kapasitas apron.'
        );

        $dispatcher = $svc->ensureRole(
            name: 'dispatcher',
            label: 'Flight Dispatcher',
            description: 'Melakukan dispatch rilis penerbangan, pengecekan kelaikan, dan weight & balance.'
        );

        $maintenancePlanner = $svc->ensureRole(
            name: 'maintenance_planner',
            label: 'Maintenance Planner',
            description: 'Merencanakan A/B/C/D check pesawat, pemantauan jam terbang, dan airworthiness release.'
        );

        $cargoAgent = $svc->ensureRole(
            name: 'cargo_agent',
            label: 'Air Cargo Agent',
            description: 'Menerima konsinyasi kargo udara, validasi dangerous goods, dan container uld.'
        );

        $customerCare = $svc->ensureRole(
            name: 'customer_care',
            label: 'Customer Care & Reservations',
            description: 'Memproses tiket penumpang, hold inventaris kursi, dan refund sesuai PM 89/2015.'
        );

        $passenger = $svc->ensureRole(
            name: 'passenger',
            label: 'Airline Passenger',
            description: 'Akses portal penumpang mandiri untuk tiket, boarding pass, dan profil perjalanan.'
        );

        // 2. Permissions
        $permissions = [
            ['name' => 'aviation.ops.view', 'label' => 'Lihat Pusat Kontrol Operasi Penerbangan', 'module' => 'Aviation'],
            ['name' => 'aviation.slots.manage', 'label' => 'Kelola Permintaan Slot Musiman Bandara', 'module' => 'Aviation'],
            ['name' => 'aviation.fleet.manage', 'label' => 'Kelola Armada dan Jadwal Perawatan Pesawat', 'module' => 'Aviation'],
            ['name' => 'aviation.dispatch.execute', 'label' => 'Eksekusi Dispatch Rilis dan Keberangkatan', 'module' => 'Aviation'],
            ['name' => 'aviation.cargo.manage', 'label' => 'Penerimaan Kargo dan Pemeriksaan Dangerous Goods', 'module' => 'Aviation'],
            ['name' => 'aviation.bookings.manage', 'label' => 'Pemesanan Tiket dan Pemrosesan Refund', 'module' => 'Aviation'],
            ['name' => 'aviation.passenger.portal', 'label' => 'Akses Portal Penumpang Mandiri', 'module' => 'Aviation'],
            ['name' => 'aviation.reports.view', 'label' => 'Akses Laporan Operasional dan Keuangan Aviasi', 'module' => 'Aviation'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission(
                name: $p['name'],
                label: $p['label'],
                module: $p['module']
            );
        }

        // 3. Assign Permissions
        $svc->grantPermissionsToRole($opsController, ['aviation.ops.view', 'aviation.dispatch.execute', 'aviation.reports.view']);
        $svc->grantPermissionsToRole($stationManager, ['aviation.slots.manage', 'aviation.ops.view']);
        $svc->grantPermissionsToRole($dispatcher, ['aviation.dispatch.execute', 'aviation.ops.view']);
        $svc->grantPermissionsToRole($maintenancePlanner, ['aviation.fleet.manage', 'aviation.ops.view']);
        $svc->grantPermissionsToRole($cargoAgent, ['aviation.cargo.manage', 'aviation.ops.view']);
        $svc->grantPermissionsToRole($customerCare, ['aviation.bookings.manage', 'aviation.reports.view']);
        $svc->grantPermissionsToRole($passenger, ['aviation.passenger.portal']);
    }
}
