<?php

declare(strict_types=1);

namespace Modules\Supplier\Console\Commands;

use Illuminate\Console\Command;
use Modules\Supplier\Application\Services\SupplierService;

class RemindSupplierCertificationsCommand extends Command
{
    protected $signature = 'sup:remind-certifications {--days=60}';

    protected $description = 'Peringatkan sertifikasi pemasok yang akan kedaluwarsa atau sudah kedaluwarsa';

    public function handle(SupplierService $service): int
    {
        $flags = $service->scanRisks();
        $certFlags = array_filter($flags, fn (array $flag): bool => in_array($flag['type'], ['expired_cert', 'expiring_cert'], true));

        foreach ($certFlags as $flag) {
            $this->warn("[{$flag['severity']}] {$flag['supplier']}: {$flag['message']}");
        }

        $this->info('Pengingat sertifikasi: '.count($certFlags).' flag.');

        return self::SUCCESS;
    }
}
