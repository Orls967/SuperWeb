<?php

declare(strict_types=1);

namespace Modules\Tlx\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Idempotently seeds RBAC roles and permissions for Lini 14 (Telko & Data Center).
 */
class TlxRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. ROLES
        $roles = [
            [
                'name' => 'telecom_admin',
                'label' => 'Telecom Administrator',
                'description' => 'Pengelolaan infrastruktur telekomunikasi, alokasi kapasitas rak, dan registry perangkat IoT grup.',
            ],
            [
                'name' => 'tlx_admin',
                'label' => 'Telecom Executive Admin',
                'description' => 'Akses penuh ke seluruh modul telekomunikasi, billing intercompany, dan NOC.',
            ],
            [
                'name' => 'noc_operator',
                'label' => 'NOC Network Operator',
                'description' => 'Monitoring alarm jaringan, korelasi topologi gangguan, dan penanganan tiket insiden MTTR.',
            ],
            [
                'name' => 'datacenter_ops',
                'label' => 'Data Center Operations Specialist',
                'description' => 'Pengelolaan rak colocation, pencatatan konsumsi daya fasilitas vs IT, dan pemantauan efisiensi PUE.',
            ],
            [
                'name' => 'telecom_billing',
                'label' => 'Telecom & Intercompany Billing Specialist',
                'description' => 'Rating CDR pemakaian data IoT, penerbitan tagihan konektivitas grup, dan billing colocation DC.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $roleModels[$r['name']] = $svc->ensureRole($r['name'], $r['label'], $r['description']);
        }

        // 2. PERMISSIONS
        $permissions = [
            ['name' => 'tlx.device.manage', 'label' => 'Kelola Registry Perangkat IoT'],
            ['name' => 'tlx.sim.manage', 'label' => 'Kelola Profil SIM & Alokasi'],
            ['name' => 'tlx.cdr.rate', 'label' => 'Rating & Agregasi CDR'],
            ['name' => 'tlx.billing.manage', 'label' => 'Kelola Penagihan Konektivitas & Intercompany'],
            ['name' => 'tlx.dc.manage', 'label' => 'Kelola Rak & Kontrak Colocation DC'],
            ['name' => 'tlx.dc.power', 'label' => 'Catat & Pantau Daya serta PUE DC'],
            ['name' => 'tlx.noc.alarms', 'label' => 'Akses & Korelasi Alarm NOC Topologi'],
            ['name' => 'tlx.noc.tickets', 'label' => 'Kelola Tiket Insiden Gangguan NOC'],
            ['name' => 'tlx.audit.view', 'label' => 'Lihat & Jalankan Audit Dua-Sumber Telko'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], 'tlx');
        }

        // 3. ROLE-PERMISSION ASSIGNMENTS
        $allPermissions = array_column($permissions, 'name');

        $svc->grantPermissionsToRole($roleModels['tlx_admin'], $allPermissions);

        $svc->grantPermissionsToRole($roleModels['telecom_admin'], [
            'tlx.device.manage',
            'tlx.sim.manage',
            'tlx.billing.manage',
            'tlx.dc.manage',
            'tlx.noc.alarms',
            'tlx.noc.tickets',
            'tlx.audit.view',
        ]);

        $svc->grantPermissionsToRole($roleModels['noc_operator'], [
            'tlx.device.manage',
            'tlx.noc.alarms',
            'tlx.noc.tickets',
        ]);

        $svc->grantPermissionsToRole($roleModels['datacenter_ops'], [
            'tlx.dc.manage',
            'tlx.dc.power',
            'tlx.audit.view',
        ]);

        $svc->grantPermissionsToRole($roleModels['telecom_billing'], [
            'tlx.device.manage',
            'tlx.cdr.rate',
            'tlx.billing.manage',
            'tlx.audit.view',
        ]);

        $adminRole = $svc->ensureRole('admin', 'Super Admin', 'Akses penuh ke seluruh platform.');
        $svc->grantPermissionsToRole($adminRole, $allPermissions);
    }
}
