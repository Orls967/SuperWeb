<?php

declare(strict_types=1);

namespace Modules\Ret\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Idempotently seeds RBAC roles and permissions for Lini 17 (Ritel & E-Commerce).
 */
class RetRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. ROLES
        $roles = [
            [
                'name' => 'marketplace_admin',
                'label' => 'Marketplace Administrator',
                'description' => 'Administrasi pasar multi-vendor, moderasi seller, komisi, dan audit escrow.',
            ],
            [
                'name' => 'store_manager',
                'label' => 'Store & Dark Store Manager',
                'description' => 'Manajemen operasional toko ritel, dark store, alokasi split pemenuhan, dan SLA.',
            ],
            [
                'name' => 'warehouse_staff',
                'label' => 'Staff Gudang & Dark Store',
                'description' => 'Pelaksanaan pick and pack di dark store, serah terima kurir, dan locker pickup.',
            ],
            [
                'name' => 'retail_ops',
                'label' => 'Retail Operations Specialist',
                'description' => 'Pengawasan omnichannel, rekonsiliasi deposit kemasan, dan integritas stok.',
            ],
            [
                'name' => 'category_mgr',
                'label' => 'Category Manager',
                'description' => 'Pengawasan listing produk, kebijakan MAP, dan margin kategori ritel.',
            ],
            [
                'name' => 'last_mile_cs',
                'label' => 'Last Mile Customer Service',
                'description' => 'Penanganan SLA breach, kompensasi voucher pelanggan, dan tracking pengiriman.',
            ],
            [
                'name' => 'customer',
                'label' => 'Retail & Super App Customer',
                'description' => 'Pelanggan belanja omnichannel, penukaran cashback, dan pengembalian wadah guna ulang.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $roleModels[$r['name']] = $svc->ensureRole($r['name'], $r['label'], $r['description']);
        }

        // 2. PERMISSIONS
        $permissions = [
            ['name' => 'ret.channel.manage', 'label' => 'Kelola Channel Ritel'],
            ['name' => 'ret.listing.moderate', 'label' => 'Moderasi Listing Seller'],
            ['name' => 'ret.pricing.map.manage', 'label' => 'Kelola Kebijakan Harga MAP'],
            ['name' => 'ret.order.view', 'label' => 'Lihat Pesanan Omnichannel'],
            ['name' => 'ret.order.checkout', 'label' => 'Checkout Pesanan Ritel'],
            ['name' => 'ret.darkstore.pickpack', 'label' => 'Operasi Pick-Pack Dark Store'],
            ['name' => 'ret.locker.pickup', 'label' => 'Proses Serah Terima Locker'],
            ['name' => 'ret.seller.settle', 'label' => 'Penyelesaian Escrow Seller'],
            ['name' => 'ret.cashback.manage', 'label' => 'Manajemen Program Cashback'],
            ['name' => 'ret.packaging.manage', 'label' => 'Kelola Deposit Kemasan Guna Ulang'],
            ['name' => 'ret.audit.view', 'label' => 'Lihat Hasil Audit Ritel'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], 'ret');
        }

        // 3. ROLE-PERMISSION ASSIGNMENT
        $svc->grantPermissionsToRole($roleModels['marketplace_admin'], [
            'ret.channel.manage',
            'ret.listing.moderate',
            'ret.pricing.map.manage',
            'ret.order.view',
            'ret.seller.settle',
            'ret.cashback.manage',
            'ret.audit.view',
        ]);

        $svc->grantPermissionsToRole($roleModels['retail_ops'], [
            'ret.channel.manage',
            'ret.order.view',
            'ret.darkstore.pickpack',
            'ret.packaging.manage',
            'ret.audit.view',
        ]);

        $svc->grantPermissionsToRole($roleModels['store_manager'], [
            'ret.order.view',
            'ret.darkstore.pickpack',
            'ret.locker.pickup',
            'ret.packaging.manage',
        ]);

        $svc->grantPermissionsToRole($roleModels['warehouse_staff'], [
            'ret.darkstore.pickpack',
            'ret.locker.pickup',
        ]);

        $svc->grantPermissionsToRole($roleModels['category_mgr'], [
            'ret.listing.moderate',
            'ret.pricing.map.manage',
            'ret.order.view',
        ]);

        $svc->grantPermissionsToRole($roleModels['last_mile_cs'], [
            'ret.order.view',
            'ret.cashback.manage',
        ]);

        $svc->grantPermissionsToRole($roleModels['customer'], [
            'ret.order.checkout',
            'ret.locker.pickup',
        ]);

        $adminRole = $svc->ensureRole('admin', 'Super Admin', 'Akses penuh ke seluruh platform.');
        $svc->grantPermissionsToRole($adminRole, array_column($permissions, 'name'));
    }
}
