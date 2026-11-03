<?php

declare(strict_types=1);

namespace Modules\Med\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Idempotently seeds RBAC roles and permissions for Lini 15 (Media & Kreatif).
 */
class MedRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. ROLES
        $roles = [
            [
                'name' => 'media_admin',
                'label' => 'Media & Creative Administrator',
                'description' => 'Akses penuh ke seluruh operasional studio, lisensi IP, dan penyelesaian royalti.',
            ],
            [
                'name' => 'studio_manager',
                'label' => 'Studio Operations Manager',
                'description' => 'Pengelolaan fasilitas studio, pemesanan slot 30-menit, dan check-in klien.',
            ],
            [
                'name' => 'rights_manager',
                'label' => 'IP Rights & Licensing Manager',
                'description' => 'Pengelolaan porsi hak cipta, kontrak lisensi wilayah, dan kalkulasi waterfall royalti.',
            ],
            [
                'name' => 'producer',
                'label' => 'Creative Production Producer',
                'description' => 'Pengelolaan anggaran produksi dan pemesanan fasilitas studio.',
            ],
            [
                'name' => 'studio_ops',
                'label' => 'Studio Operator',
                'description' => 'Operasional harian dan pemeliharaan fasilitas panggung produksi.',
            ],
            [
                'name' => 'ip_manager',
                'label' => 'Intellectual Property Specialist',
                'description' => 'Registrasi aset IP dan pemantauan perlindungan hak cipta.',
            ],
            [
                'name' => 'talent_mgmt',
                'label' => 'Talent Management Specialist',
                'description' => 'Pengelolaan kontrak talent dan pembayaran royalti residual.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $roleModels[$r['name']] = $svc->ensureRole($r['name'], $r['label'], $r['description']);
        }

        // 2. PERMISSIONS
        $permissions = [
            ['name' => 'med.studios.view', 'label' => 'View Media Studios'],
            ['name' => 'med.studios.manage', 'label' => 'Manage Media Studios and Bookings'],
            ['name' => 'med.ip.view', 'label' => 'View IP Assets and Licenses'],
            ['name' => 'med.ip.manage', 'label' => 'Manage IP Assets, Shares, and Licenses'],
            ['name' => 'med.royalties.view', 'label' => 'View Royalty Statements'],
            ['name' => 'med.royalties.settle', 'label' => 'Settle Waterfall Royalties'],
            ['name' => 'med.ads.manage', 'label' => 'Manage Ad Placements and Campaigns'],
        ];

        $permModels = [];
        foreach ($permissions as $p) {
            $permModels[$p['name']] = $svc->ensurePermission($p['name'], $p['label']);
        }

        // 3. ROLE-PERMISSION MAPPINGS
        $matrix = [
            'media_admin' => [
                'med.studios.view', 'med.studios.manage',
                'med.ip.view', 'med.ip.manage',
                'med.royalties.view', 'med.royalties.settle',
                'med.ads.manage',
            ],
            'studio_manager' => [
                'med.studios.view', 'med.studios.manage',
            ],
            'rights_manager' => [
                'med.ip.view', 'med.ip.manage',
                'med.royalties.view', 'med.royalties.settle',
            ],
            'producer' => [
                'med.studios.view', 'med.studios.manage',
                'med.ip.view',
            ],
            'studio_ops' => [
                'med.studios.view',
            ],
            'ip_manager' => [
                'med.ip.view', 'med.ip.manage',
            ],
            'talent_mgmt' => [
                'med.royalties.view',
            ],
        ];

        foreach ($matrix as $roleName => $perms) {
            if (isset($roleModels[$roleName])) {
                $svc->syncRolePermissions($roleModels[$roleName], $perms);
            }
        }
    }
}
