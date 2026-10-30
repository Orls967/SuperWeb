<?php

declare(strict_types=1);

namespace Modules\Rwa\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

class RwaRbacSeeder extends Seeder
{
    public function run(): void
    {
        $rbac = app(RbacService::class);

        // Roles
        $issuer = $rbac->ensureRole('rwa_issuer', 'RWA Issuer', 'Penerbit aset dunia nyata dan penawaran token.');
        $compliance = $rbac->ensureRole('rwa_compliance_officer', 'RWA Compliance Officer', 'Petugas kepatuhan dan persetujuan emisi RWA.');
        $investor = $rbac->ensureRole('rwa_investor', 'RWA Investor', 'Investor ritel/institusional aset RWA.');

        // Permissions
        $permissions = [
            'rwa.asset.create' => 'Membuat draft penerbitan aset RWA',
            'rwa.asset.approve' => 'Menyetujui penerbitan aset RWA (four-eyes)',
            'rwa.offering.create' => 'Membuat penawaran primer IPO RWA',
            'rwa.offering.subscribe' => 'Memesan token pada penawaran primer RWA',
            'rwa.orderbook.trade' => 'Melakukan trading pada secondary market RWA',
            'rwa.dividend.declare' => 'Mendeklarasikan dividen yield RWA',
            'rwa.dividend.claim' => 'Menerima dividen hasil investasi RWA',
            'rwa.investor.verify' => 'Memverifikasi status KYC investor RWA',
            'rwa.view' => 'Melihat katalog dan portofolio RWA',
        ];

        foreach ($permissions as $permName => $permLabel) {
            $rbac->ensurePermission($permName, $permLabel, 'rwa');
        }

        $rbac->grantPermissionsToRole($issuer, [
            'rwa.asset.create',
            'rwa.offering.create',
            'rwa.dividend.declare',
            'rwa.view',
        ]);

        $rbac->grantPermissionsToRole($compliance, [
            'rwa.asset.approve',
            'rwa.investor.verify',
            'rwa.view',
        ]);

        $rbac->grantPermissionsToRole($investor, [
            'rwa.offering.subscribe',
            'rwa.orderbook.trade',
            'rwa.dividend.claim',
            'rwa.view',
        ]);
    }
}
