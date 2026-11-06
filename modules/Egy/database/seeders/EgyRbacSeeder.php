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
                'description' => 'Operasi grid, smart meter telemetry, merit order dispatch, microgrid.',
            ],
            [
                'name' => 'energy_billing',
                'label' => 'Billing Utilitas & Energi',
                'description' => 'Manajemen tagihan TOU, demand charge, net metering, dan konsolidasi invoice.',
            ],
            [
                'name' => 'genco_trader',
                'label' => 'Genco Energy Trader',
                'description' => 'Perdagangan energi, unit commitment pembangkit, PPA antar-entitas, arbitrase baterai.',
            ],
            [
                'name' => 'energy_auditor',
                'label' => 'Auditor Energi & ESCO',
                'description' => 'Audit register meter, verifikasi sertifikat numerik, rekonsiliasi M&V ESCO.',
            ],
            [
                'name' => 'renewable_dev',
                'label' => 'Pengembang Energi Terbarukan',
                'description' => 'Monitoring REC, aset baterai storage, perencanaan surya microgrid.',
            ],
            [
                'name' => 'utility_operator',
                'label' => 'Operator Utilitas Kawasan',
                'description' => 'Pengelolaan air IPA, waste-to-energy, dan district cooling.',
            ],
            [
                'name' => 'carbon_trader',
                'label' => 'Trader Karbon & ESG',
                'description' => 'Perdagangan kredit karbon internal, orderbook bursa, paket ESG.',
            ],
            [
                'name' => 'export_officer',
                'label' => 'Petugas Ekspor & CBAM',
                'description' => 'Kalkulasi sertifikat CBAM kontainer dan kepatuhan perbatasan karbon.',
            ],
            [
                'name' => 'compliance_officer',
                'label' => 'Petugas Kepatuhan Cadangan',
                'description' => 'Kepatuhan uji beban berkala genset/cadangan RS, venue, data center.',
            ],
            [
                'name' => 'energy_exec',
                'label' => 'Eksekutif Ketahanan Energi',
                'description' => 'Pemantauan scorecard ketahanan, SAIDI/SAIFI, ketersediaan energi 17 lini.',
            ],
            [
                'name' => 'customer',
                'label' => 'Pelanggan Utilitas',
                'description' => 'Portal pelanggan untuk monitoring pemakaian daya dan pembayaran tagihan.',
            ],
        ];

        foreach ($roles as $r) {
            $svc->ensureRole($r['name'], $r['label'], $r['description']);
        }
    }
}
