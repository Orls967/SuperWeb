<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;
use Modules\Core\Domain\Models\Role;

/**
 * Seeds the RBAC tables with all platform roles, permissions, and backfills
 * existing users from their legacy `users.role` column.
 *
 * Idempotent — safe to run multiple times.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // ============================================================
        // 1. ROLES — mirror all existing `users.role` values + future
        // ============================================================
        $roles = [
            ['name' => 'admin', 'label' => 'Super Admin', 'description' => 'Akses penuh ke seluruh platform.'],
            ['name' => 'customer', 'label' => 'Customer', 'description' => 'Pelanggan: belanja, booking, wallet.'],
            ['name' => 'mekanik', 'label' => 'Mekanik', 'description' => 'Teknisi bengkel AutoServe.'],
            ['name' => 'tenant', 'label' => 'Tenant Mall', 'description' => 'Penyewa unit di Duta Mall.'],
            ['name' => 'outlet_manager', 'label' => 'Manajer Outlet', 'description' => 'Manajer outlet restoran.'],
            ['name' => 'kitchen', 'label' => 'Dapur', 'description' => 'Staf dapur restoran.'],
            ['name' => 'cashier', 'label' => 'Kasir', 'description' => 'Kasir POS restoran.'],
            ['name' => 'shipper', 'label' => 'Shipper', 'description' => 'Pengirim barang B2B/B2C.'],
            ['name' => 'driver', 'label' => 'Driver', 'description' => 'Pengemudi armada logistik.'],
            ['name' => 'dispatcher', 'label' => 'Dispatcher', 'description' => 'Petugas dispatch logistik.'],
            ['name' => 'hub_operator', 'label' => 'Operator Hub', 'description' => 'Operator hub/gudang logistik.'],
            ['name' => 'logistics_admin', 'label' => 'Admin Logistik', 'description' => 'Admin modul logistik.'],
            ['name' => 'party_manager', 'label' => 'Manajer Pihak', 'description' => 'Mengelola direktori pihak & badan hukum.'],
            ['name' => 'contract_manager', 'label' => 'Manajer Kontrak', 'description' => 'Mengelola kontrak, klausul, dan template.'],
            ['name' => 'legal', 'label' => 'Legal', 'description' => 'Meninjau, menegosiasi, dan menyetujui kontrak.'],
            ['name' => 'asset_manager', 'label' => 'Manajer Aset', 'description' => 'Mengelola register aset, mutasi, opname, asuransi.'],
            ['name' => 'auditor', 'label' => 'Auditor', 'description' => 'Hanya baca untuk audit dan rekonsiliasi.'],
        ];

        foreach ($roles as $r) {
            $svc->ensureRole($r['name'], $r['label'], $r['description']);
        }

        // ============================================================
        // 2. PERMISSIONS — grouped by module
        // ============================================================
        $permissions = [
            // -- Core / Platform --
            'core' => [
                'core.dashboard.view' => 'Lihat dashboard',
                'core.health.view' => 'Lihat kesehatan sistem',
                'core.audit_log.view' => 'Lihat audit log',
                'core.vehicle.manage' => 'Kelola kendaraan',
                'core.user.manage' => 'Kelola pengguna',
                'core.rbac.manage' => 'Kelola RBAC (role & permission)',
            ],
            // -- Banking --
            'banking' => [
                'banking.wallet.view' => 'Lihat wallet sendiri',
                'banking.wallet.transfer' => 'Transfer saldo',
                'banking.ledger.view' => 'Lihat buku besar',
                'banking.ledger.adjust' => 'Manual adjustment ledger',
                'banking.reconcile' => 'Jalankan rekonsiliasi',
            ],
            // -- Store --
            'store' => [
                'store.catalog.view' => 'Lihat katalog',
                'store.order.create' => 'Buat order',
                'store.admin.manage' => 'Kelola admin store',
                'store.product.manage' => 'Kelola produk',
            ],
            // -- AutoServe --
            'autoserve' => [
                'autoserve.booking.create' => 'Buat booking bengkel',
                'autoserve.booking.manage' => 'Kelola booking bengkel',
                'autoserve.invoice.manage' => 'Kelola invoice bengkel',
                'autoserve.sparepart.manage' => 'Kelola sparepart',
            ],
            // -- Crypto --
            'crypto' => [
                'crypto.trade' => 'Trading kripto',
                'crypto.portfolio.view' => 'Lihat portfolio',
            ],
            // -- Finance --
            'finance' => [
                'finance.loan.create' => 'Buat pinjaman',
                'finance.loan.manage' => 'Kelola pinjaman',
            ],
            // -- Resto --
            'resto' => [
                'resto.menu.manage' => 'Kelola menu restoran',
                'resto.kitchen.operate' => 'Operasi dapur',
                'resto.pos.operate' => 'Operasi POS kasir',
                'resto.shift.manage' => 'Kelola shift kasir',
                'resto.outlet.manage' => 'Kelola outlet',
                'resto.purchase.manage' => 'Kelola pembelian bahan',
                'resto.analytics.view' => 'Lihat analitik restoran',
                'resto.franchise.manage' => 'Kelola franchise & royalti',
            ],
            // -- Mall --
            'mall' => [
                'mall.lease.manage' => 'Kelola sewa unit',
                'mall.billing.manage' => 'Kelola tagihan',
                'mall.parking.operate' => 'Operasi parkir',
                'mall.tenant_portal.access' => 'Akses portal tenant',
                'mall.loyalty.manage' => 'Kelola loyalty & voucher',
                'mall.event.manage' => 'Kelola event & atrium',
                'mall.facility.manage' => 'Kelola fasilitas & WO',
                'mall.analytics.view' => 'Lihat analitik mall',
            ],
            // -- Logistics --
            'logistics' => [
                'logistics.shipment.create' => 'Buat shipment',
                'logistics.shipment.manage' => 'Kelola shipment',
                'logistics.dispatch.manage' => 'Kelola dispatch',
                'logistics.hub.operate' => 'Operasi hub',
                'logistics.driver.operate' => 'Operasi driver',
                'logistics.fleet.manage' => 'Kelola armada',
                'logistics.cod.manage' => 'Kelola COD',
                'logistics.carrier.manage' => 'Kelola carrier & subkontrak',
                'logistics.claim.manage' => 'Kelola klaim',
                'logistics.customs.manage' => 'Kelola bea cukai',
                'logistics.billing.manage' => 'Kelola billing logistik',
                'logistics.control_tower.view' => 'Lihat control tower',
                'logistics.api.access' => 'Akses API logistik',
            ],

            // -- Party & Contract --
            'party' => [
                'party.view' => 'Lihat direktori pihak',
                'party.manage' => 'Kelola pihak & KYC',
                'party.legal_entity.manage' => 'Kelola badan hukum',
            ],
            'contract' => [
                'contract.view' => 'Lihat kontrak',
                'contract.manage' => 'Kelola kontrak, klausul, dan template',
                'contract.approve' => 'Menyetujui & menandatangani kontrak',
            ],
            'asset' => [
                'asset.view' => 'Lihat register aset',
                'asset.manage' => 'Registrasi, mutasi, opname, dan asuransi aset',
                'asset.approve' => 'Menyetujui mutasi & penyesuaian aset',
            ],
        ];

        foreach ($permissions as $module => $perms) {
            foreach ($perms as $name => $label) {
                $svc->ensurePermission($name, $label, $module);
            }
        }

        // ============================================================
        // 3. ROLE → PERMISSION MAPPING
        // ============================================================
        $rolePermissionMap = [
            // Admin — gets everything
            'admin' => array_keys(array_merge(...array_values($permissions))),

            'customer' => [
                'core.dashboard.view', 'core.vehicle.manage',
                'banking.wallet.view', 'banking.wallet.transfer',
                'store.catalog.view', 'store.order.create',
                'autoserve.booking.create',
                'crypto.trade', 'crypto.portfolio.view',
                'finance.loan.create',
                'mall.tenant_portal.access',
            ],

            'mekanik' => [
                'core.dashboard.view',
                'autoserve.booking.manage', 'autoserve.sparepart.manage',
            ],

            'tenant' => [
                'core.dashboard.view',
                'mall.tenant_portal.access',
            ],

            'outlet_manager' => [
                'core.dashboard.view',
                'resto.menu.manage', 'resto.outlet.manage', 'resto.shift.manage',
                'resto.purchase.manage', 'resto.analytics.view', 'resto.franchise.manage',
                'resto.kitchen.operate', 'resto.pos.operate',
            ],

            'kitchen' => [
                'core.dashboard.view',
                'resto.kitchen.operate',
            ],

            'cashier' => [
                'core.dashboard.view',
                'resto.pos.operate', 'resto.shift.manage',
            ],

            'shipper' => [
                'core.dashboard.view',
                'logistics.shipment.create',
                'logistics.api.access',
            ],

            'driver' => [
                'core.dashboard.view',
                'logistics.driver.operate',
            ],

            'dispatcher' => [
                'core.dashboard.view',
                'logistics.shipment.manage', 'logistics.dispatch.manage',
                'logistics.fleet.manage',
            ],

            'hub_operator' => [
                'core.dashboard.view',
                'logistics.hub.operate',
            ],

            'logistics_admin' => [
                'core.dashboard.view',
                'logistics.shipment.create', 'logistics.shipment.manage',
                'logistics.dispatch.manage', 'logistics.hub.operate',
                'logistics.fleet.manage', 'logistics.cod.manage',
                'logistics.carrier.manage', 'logistics.claim.manage',
                'logistics.customs.manage', 'logistics.billing.manage',
                'logistics.control_tower.view', 'logistics.api.access',
            ],
            'party_manager' => [
                'core.dashboard.view',
                'party.view', 'party.manage', 'party.legal_entity.manage',
                'contract.view',
            ],

            'contract_manager' => [
                'core.dashboard.view',
                'party.view',
                'contract.view', 'contract.manage', 'contract.approve',
            ],

            'legal' => [
                'core.dashboard.view',
                'party.view',
                'contract.view', 'contract.approve',
            ],

            'asset_manager' => [
                'core.dashboard.view',
                'asset.view', 'asset.manage', 'asset.approve',
            ],

            'auditor' => [
                'core.dashboard.view',
                'party.view', 'contract.view', 'asset.view',
            ],
        ];

        foreach ($rolePermissionMap as $roleName => $permNames) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $svc->grantPermissionsToRole($role, $permNames);
            }
        }

        // ============================================================
        // 4. BACKFILL existing users from legacy users.role column
        // ============================================================
        $svc->backfillAllFromLegacy();
    }
}
