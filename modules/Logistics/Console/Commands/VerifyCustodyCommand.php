<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;

class VerifyCustodyCommand extends Command
{
    protected $signature = 'lgx:verify-custody {--shipment_id= : Verifikasi ID pengiriman tertentu}';

    protected $description = 'Verifikasi integritas kriptografis rantai lacak balak (Chain of Custody) seluruh pengiriman';

    public function handle(): int
    {
        $this->info('Memulai verifikasi integritas rantai lacak balak (Chain of Custody) logistik...');

        $query = Shipment::query();
        if ($this->option('shipment_id')) {
            $query->where('id', (int) $this->option('shipment_id'));
        }

        $totalShipments = 0;
        $totalEvents = 0;
        $corruptions = [];

        $query->chunkById(100, function ($shipments) use (&$totalShipments, &$totalEvents, &$corruptions) {
            foreach ($shipments as $shipment) {
                $totalShipments++;
                $events = TrackingEvent::where('shipment_id', $shipment->id)
                    ->orderBy('sequence', 'asc')
                    ->get();

                if ($events->isEmpty()) {
                    continue;
                }

                $expectedPrevHash = TrackingEvent::genesisHash($shipment->id);
                $expectedSequence = 1;

                foreach ($events as $event) {
                    $totalEvents++;

                    // 1. Sequence check
                    if ($event->sequence !== $expectedSequence) {
                        $corruptions[] = [
                            'shipment' => $shipment->tracking_number,
                            'event_id' => $event->id,
                            'reason' => "Urutan sekuens terputus: Diharapkan {$expectedSequence}, ditemukan {$event->sequence}",
                        ];
                    }

                    // 2. Previous hash chain linkage check
                    if ($event->prev_hash !== $expectedPrevHash) {
                        $corruptions[] = [
                            'shipment' => $shipment->tracking_number,
                            'event_id' => $event->id,
                            'reason' => 'Tautan prev_hash tidak cocok dengan hash blok sebelumnya!',
                        ];
                    }

                    // 3. Re-calculate SHA-256 canonical hash
                    $recalculatedHash = TrackingEvent::calculateHash(
                        prevHash: $event->prev_hash,
                        sequence: $event->sequence,
                        eventType: $event->event_type,
                        payload: $event->payload ?? [],
                        occurredAt: $event->occurred_at
                    );

                    if ($recalculatedHash !== $event->hash) {
                        $corruptions[] = [
                            'shipment' => $shipment->tracking_number,
                            'event_id' => $event->id,
                            'reason' => 'Hash SHA-256 tidak valid! Terdeteksi manipulasi data event.',
                        ];
                    }

                    $expectedPrevHash = $event->hash;
                    $expectedSequence++;
                }
            }
        });

        if (! empty($corruptions)) {
            $this->error('❌ Terdeteksi '.count($corruptions).' pelanggaran integritas rantai lacak balak!');
            $this->table(['No. Resi', 'Event ID', 'Penyebab Kerusakan'], $corruptions);

            return self::FAILURE;
        }

        $this->info("✓ Seluruh {$totalShipments} pengiriman ({$totalEvents} event) rantai lacak balak terverifikasi valid dan bebas manipulasi.");

        return self::SUCCESS;
    }
}
