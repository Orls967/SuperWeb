<?php

declare(strict_types=1);

namespace Modules\TradeFinance\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

class TradeFinanceRbacSeeder extends Seeder
{
    public function run(): void
    {
        $rbac = app(RbacService::class);

        $rbac->ensureRole('tf_officer', 'Trade Finance Officer', 'Petugas clearing escrow USDT dan rilis berbasis verifikasi custody.');
        $rbac->ensureRole('importer', 'Importir', 'Pihak importir pendana escrow clearing.');
        $rbac->ensureRole('exporter', 'Eksportir', 'Pihak eksportir penerima rilis escrow clearing.');
        $rbac->ensureRole('compliance_officer', 'Compliance Officer', 'Petugas kepatuhan sertifikasi CBAM.');
    }
}
