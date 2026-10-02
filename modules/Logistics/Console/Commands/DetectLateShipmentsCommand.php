<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Actions\RaiseShipmentExceptionAction;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Services\DeliverySlaPolicy;

class DetectLateShipmentsCommand extends Command
{
    protected $signature = 'lgx:detect-late {--dry-run : Hitung tanpa menyimpan exception}';

    protected $description = 'Deteksi resi yang melampaui SLA dan tandai exception "late" (idempoten)';

    public function handle(DeliverySlaPolicy $sla, RaiseShipmentExceptionAction $raise): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $existing = 0;

        $sla->breachedQuery()->chunkById(500, function ($shipments) use ($sla, $raise, $dryRun, &$created, &$existing) {
            foreach ($shipments as $shipment) {
                $key = 'late:'.$shipment->id;

                if (ShipmentException::where('dedupe_key', $key)->exists()) {
                    $existing++;

                    continue;
                }

                $created++;
                if ($dryRun) {
                    continue;
                }

                $due = $sla->dueAt($shipment);
                $raise->execute(
                    shipment: $shipment,
                    type: ExceptionType::Late,
                    description: "SLA {$shipment->service_level->label()} terlampaui; jatuh tempo {$due->format('d/m/Y H:i')}.",
                    dedupeKey: $key,
                    payload: ['due_at' => $due->toIso8601String(), 'service_level' => $shipment->service_level->value],
                );
            }
        });

        // Exception "late" pada resi yang sudah selesai (terkirim/dikembalikan/hilang) ditutup otomatis.
        $closed = 0;
        if (! $dryRun) {
            $closed = ShipmentException::where('type', ExceptionType::Late->value)
                ->where('status', ShipmentException::STATUS_OPEN)
                ->whereHas('shipment', fn ($q) => $q->whereIn('status', [
                    ShipmentStatus::Delivered->value,
                    ShipmentStatus::Returned->value,
                    ShipmentStatus::Lost->value,
                    ShipmentStatus::Cancelled->value,
                ]))
                ->update([
                    'status' => ShipmentException::STATUS_RESOLVED,
                    'resolved_at' => now(),
                    'resolution_notes' => 'Ditutup otomatis: resi telah selesai.',
                ]);
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Exception terlambat baru: {$created}; sudah tercatat: {$existing}; ditutup otomatis: {$closed}.");

        return self::SUCCESS;
    }
}
