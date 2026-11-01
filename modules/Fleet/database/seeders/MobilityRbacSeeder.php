<?php

declare(strict_types=1);

namespace Modules\Fleet\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Seeder RBAC khusus Mobility (Telematics, EV, Fleet) (KONSEP.md §A6, §1F, U-5, Z8).
 * Idempoten dan aman dijalankan berulang kali.
 */
class MobilityRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        $svc->ensureRole(
            name: 'fleet_manager',
            label: 'Manajer Armada',
            description: 'Mengelola kontrak sewa armada, unit kendaraan, SLA dan telematika.'
        );

        $svc->ensureRole(
            name: 'ev_operator',
            label: 'Operator SPKLU EV',
            description: 'Memantau dan mengelola stasiun, charger, dan sesi pengisian EV.'
        );

        $svc->ensureRole(
            name: 'station_operator',
            label: 'Petugas Stasiun SPKLU',
            description: 'Petugas lapangan stasiun pengisian daya kendaraan listrik.'
        );

        $svc->ensureRole(
            name: 'driver',
            label: 'Pengemudi Armada / EV',
            description: 'Pengemudi kendaraan armada dan pengguna sesi pengisian SPKLU.'
        );

        $svc->ensureRole(
            name: 'finance_officer_oto',
            label: 'Petugas Keuangan Mobilitas',
            description: 'Mengelola penagihan invoice sewa bulanan dan rekonsiliasi revenue EV.'
        );

        $svc->ensureRole(
            name: 'mechanic',
            label: 'Mekanik Bengkel',
            description: 'Melakukan perbaikan dan persetujuan pelepasan status grounded.'
        );
    }
}
