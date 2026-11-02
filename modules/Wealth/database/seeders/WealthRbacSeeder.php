<?php

declare(strict_types=1);

namespace Modules\Wealth\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

class WealthRbacSeeder extends Seeder
{
    public function run(): void
    {
        $rbac = app(RbacService::class);

        // Roles
        $advisor = $rbac->ensureRole('wealth_advisor', 'Wealth Advisor', 'Penasihat investasi dan rebalancing portofolio.');
        $investor = $rbac->ensureRole('wealth_investor', 'Wealth Investor', 'Investor wealth management dan automated cash sweep.');

        // Permissions
        $permissions = [
            'wealth.profile.view' => 'Melihat profil risiko dan alokasi nasabah',
            'wealth.nav.update' => 'Mencatat pembaruan nilai aktiva bersih (NAV)',
            'wealth.rebalance.execute' => 'Mengeksekusi rebalancing portofolio drift',
            'wealth.fee.charge' => 'Membebankan biaya pengelolaan manajemen kekayaan',
            'wealth.survey.submit' => 'Mengisi survei profil toleransi risiko',
            'wealth.sweep.configure' => 'Mengatur parameter auto-sweep idle cash',
            'wealth.portfolio.view' => 'Melihat kepemilikan unit dan valuasi MTM',
        ];

        foreach ($permissions as $permName => $permLabel) {
            $rbac->ensurePermission($permName, $permLabel, 'wealth');
        }

        $rbac->grantPermissionsToRole($advisor, [
            'wealth.profile.view',
            'wealth.nav.update',
            'wealth.rebalance.execute',
            'wealth.fee.charge',
        ]);

        $rbac->grantPermissionsToRole($investor, [
            'wealth.survey.submit',
            'wealth.sweep.configure',
            'wealth.portfolio.view',
        ]);
    }
}
