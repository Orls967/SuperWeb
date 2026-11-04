<?php

declare(strict_types=1);

namespace Modules\Syariah\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

class SyariahRbacSeeder extends Seeder
{
    public function run(): void
    {
        $rbac = app(RbacService::class);

        $rbac->ensureRole('syariah_officer', 'Syariah Financing Officer', 'Petugas operasional pembiayaan murabahah syariah.');
        $rbac->ensureRole('dps_reviewer', 'Dewan Pengawas Syariah Reviewer', 'Reviewer independen kepatuhan fatwa dan bebas riba (four-eyes).');
        $rbac->ensureRole('nasabah', 'Nasabah Syariah', 'Nasabah penerima pembiayaan murabahah.');
    }
}
