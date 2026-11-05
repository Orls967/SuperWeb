<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Syariah\Application\Services\IjarahFacilityService;
use Modules\Syariah\Domain\Enums\IjarahStatus;
use Modules\Syariah\Domain\Models\SybIjarahFacility;
use Modules\Syariah\Domain\Models\SybIjarahFacilitySchedule;

final class IjarahBillingScheduledCommand extends Command
{
    protected $signature = 'syb:ijarah-billing {--facility-id= : ID fasilitas spesifik}';

    protected $description = 'Proses terjadwal idempoten penagihan ujrah dan penyusutan berkala akad ijarah';

    public function handle(IjarahFacilityService $facilityService, ClockInterface $clock): int
    {
        $this->info('Memulai pemrosesan penagihan ujrah dan amortisasi sewa ijarah...');

        $query = SybIjarahFacility::query()->where('status', IjarahStatus::ACTIVE);
        if ($this->option('facility-id')) {
            $query->where('id', (int) $this->option('facility-id'));
        }

        $facilities = $query->get();
        $processed = 0;

        foreach ($facilities as $facility) {
            $unpaidSchedule = SybIjarahFacilitySchedule::query()
                ->where('facility_id', $facility->id)
                ->where('status', 'UNPAID')
                ->where('due_date', '<=', $clock->now()->toDateString())
                ->orderBy('period')
                ->first();

            if ($unpaidSchedule !== null) {
                $idempKey = "cron:ijr:bill:{$facility->id}:{$unpaidSchedule->period}:{$unpaidSchedule->due_date}";
                $facilityService->payPeriod(
                    facilityId: $facility->id,
                    period: $unpaidSchedule->period,
                    payer: $facility->customer,
                    idempotencyKey: $idempKey
                );
                $processed++;
            }
        }

        $this->info("Penagihan ujrah ijarah selesai: {$processed} jadwal diproses.");

        return self::SUCCESS;
    }
}
