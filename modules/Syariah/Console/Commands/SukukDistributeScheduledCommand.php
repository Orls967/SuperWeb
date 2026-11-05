<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Syariah\Application\Services\SukukFacilityService;
use Modules\Syariah\Domain\Enums\SukukStatus;
use Modules\Syariah\Domain\Models\SybSukukFacility;

final class SukukDistributeScheduledCommand extends Command
{
    protected $signature = 'syb:sukuk-distribute {period : Kode periode distribusi kupon, misal 2026-Q1}';

    protected $description = 'Proses terjadwal idempoten distribusi kupon/ujrah sukuk ke pemegang token';

    public function handle(SukukFacilityService $facilityService): int
    {
        $period = (string) $this->argument('period');
        $this->info("Menjalankan distribusi kupon sukuk periode {$period}...");

        $sukuks = SybSukukFacility::query()
            ->whereIn('status', [SukukStatus::ISSUED, SukukStatus::DISTRIBUTING])
            ->get();

        $admin = User::first() ?? User::factory()->create();
        $count = 0;

        foreach ($sukuks as $sukuk) {
            $quarterlyPool = (int) intdiv($sukuk->face_value_idr * $sukuk->annual_coupon_bps, 40000);
            if ($quarterlyPool > 0) {
                $idempKey = "cron:skk:dist:{$sukuk->id}:{$period}";
                $facilityService->distributeCoupon(
                    facilityId: $sukuk->id,
                    periodCode: $period,
                    couponPoolIdr: $quarterlyPool,
                    idempotencyKey: $idempKey,
                    officer: $admin
                );
                $count++;
            }
        }

        $this->info("Distribusi kupon sukuk selesai: {$count} fasilitas sukuk diproses.");

        return self::SUCCESS;
    }
}
