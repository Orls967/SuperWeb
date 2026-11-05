<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Syariah\Application\Services\SyariahOperationsCommandCenterService;
use Modules\Syariah\Domain\Models\SybHalalMerchant;

final class HalalCertificateExpirySweepCommand extends Command
{
    protected $signature = 'syb:halal-expiry-sweep';

    protected $description = 'Sweep harian untuk mendeteksi dan menonaktifkan sertifikat halal merchant yang telah kedaluwarsa';

    public function handle(SyariahOperationsCommandCenterService $commandCenter): int
    {
        $this->info('Memindai masa berlaku sertifikat halal merchant...');

        $now = Carbon::now()->toDateString();
        $expiredMerchants = SybHalalMerchant::where('status', 'ACTIVE')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        $count = 0;
        foreach ($expiredMerchants as $m) {
            $m->update(['status' => 'EXPIRED']);
            $count++;

            $commandCenter->recordIncident(
                severity: 'HIGH',
                category: 'EXPIRED_HALAL_CERTIFICATE',
                description: "Sertifikat halal merchant {$m->merchant_code} ({$m->name}) kedaluwarsa pada {$m->expires_at->toDateString()}.",
                entityReference: $m->merchant_code
            );
        }

        $this->info("Sweep selesai. {$count} merchant telah dinonaktifkan status halal-nya.");

        return Command::SUCCESS;
    }
}
