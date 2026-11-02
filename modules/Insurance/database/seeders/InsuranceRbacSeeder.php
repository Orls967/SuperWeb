<?php

declare(strict_types=1);

namespace Modules\Insurance\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

class InsuranceRbacSeeder extends Seeder
{
    public function run(): void
    {
        $rbac = app(RbacService::class);

        // Roles
        $underwriter = $rbac->ensureRole('insurance_underwriter', 'Insurance Underwriter', 'Underwriter penerbit polis dan konfigurasi oracle.');
        $claimsOfficer = $rbac->ensureRole('insurance_claims_officer', 'Insurance Claims Officer', 'Petugas investigasi dan penyelesaian klaim.');

        // Permissions
        $permissions = [
            'insurance.policy.issue' => 'Menerbitkan polis asuransi parametrik/mikro',
            'insurance.oracle.register' => 'Mendaftarkan oracle trigger parametrik',
            'insurance.claim.investigate' => 'Investigasi manual klaim terindikasi fraud',
            'insurance.claim.approve' => 'Menyetujui klaim yang lolos review',
            'insurance.claim.reject' => 'Menolak klaim fraudulent',
            'insurance.claim.settle' => 'Mengeksekusi penyelesaian pembayaran ganti rugi',
            'insurance.view' => 'Melihat polis dan riwayat klaim asuransi',
        ];

        foreach ($permissions as $permName => $permLabel) {
            $rbac->ensurePermission($permName, $permLabel, 'insurance');
        }

        $rbac->grantPermissionsToRole($underwriter, [
            'insurance.policy.issue',
            'insurance.oracle.register',
            'insurance.view',
        ]);

        $rbac->grantPermissionsToRole($claimsOfficer, [
            'insurance.claim.investigate',
            'insurance.claim.approve',
            'insurance.claim.reject',
            'insurance.claim.settle',
            'insurance.view',
        ]);
    }
}
