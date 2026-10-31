<?php

declare(strict_types=1);

namespace Modules\Venue\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Seeder RBAC khusus modul Venue (KONSEP.md §A6, Keputusan Pemilik U-5, Z8).
 * Idempoten dan aman dijalankan berulang kali.
 */
class VenueRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. Roles
        $venueManager = $svc->ensureRole(
            name: 'venue_manager',
            label: 'Manajer Venue',
            description: 'Mengelola event, zona, harga tiket, dan meja VIP venue.'
        );

        $venueStaff = $svc->ensureRole(
            name: 'venue_staff',
            label: 'Staf Gerbang Venue',
            description: 'Petugas turnstile pemindaian tiket masuk dan validasi usia.'
        );

        $artistRelations = $svc->ensureRole(
            name: 'artist_relations',
            label: 'Hubungan Artis',
            description: 'Mengelola kontrak artis, door share, dan settlement pajak.'
        );

        // 2. Permissions
        $permissions = [
            ['name' => 'venue.event.create', 'label' => 'Buat Event Venue', 'module' => 'Venue'],
            ['name' => 'venue.event.view', 'label' => 'Lihat Event Venue', 'module' => 'Venue'],
            ['name' => 'venue.event.close', 'label' => 'Tutup Event & Akui Pendapatan', 'module' => 'Venue'],
            ['name' => 'venue.gate.scan', 'label' => 'Pindai Tiket di Gerbang Turnstile', 'module' => 'Venue'],
            ['name' => 'venue.table.manage', 'label' => 'Kelola Meja VIP & Bottle Service', 'module' => 'Venue'],
            ['name' => 'venue.artist.settle', 'label' => 'Settle Kontrak & Fee Artis', 'module' => 'Venue'],
            ['name' => 'venue.audit.view', 'label' => 'Lihat Laporan Audit Venue', 'module' => 'Venue'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], $p['module']);
        }

        // 3. Grant Permissions to Roles
        $svc->grantPermissionsToRole($venueManager, [
            'venue.event.create',
            'venue.event.view',
            'venue.event.close',
            'venue.gate.scan',
            'venue.table.manage',
            'venue.audit.view',
        ]);

        $svc->grantPermissionsToRole($venueStaff, [
            'venue.gate.scan',
            'venue.event.view',
        ]);

        $svc->grantPermissionsToRole($artistRelations, [
            'venue.artist.settle',
            'venue.event.view',
        ]);
    }
}
