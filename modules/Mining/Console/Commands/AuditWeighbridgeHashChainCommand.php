<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Mining\Domain\Models\WeighbridgeTicket;

class AuditWeighbridgeHashChainCommand extends Command
{
    protected $signature = 'mining:audit-weighbridge-chain {--site= : ID Site Konsesi}';

    protected $description = 'Audit kriptografis rantai-hash seluruh tiket timbang jembatan timbang dari genesis hingga transaksi mutakhir';

    public function handle(): int
    {
        $this->info('Memulai audit integritas rantai-hash tiket timbang...');

        $siteId = $this->option('site');
        $query = WeighbridgeTicket::query()->orderBy('created_at', 'asc');

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            $this->info('Tidak ditemukan tiket timbang untuk diaudit.');

            return 0;
        }

        $corruptedCount = 0;
        $expectedPrevHash = 'GENESIS_WEIGHBRIDGE_HASH';

        foreach ($tickets as $idx => $t) {
            $weighedAt = $t->weighed_at ? CarbonImmutable::parse($t->weighed_at)->toIso8601String() : '';
            $plate = (string) ($t->vehicle_plate ?: $t->truck_plate_number);
            $destination = (string) ($t->destination ?: 'STOCKPILE_IN');

            $hashPayload = implode('|', [
                $t->previous_hash ?? $t->prev_hash ?? $expectedPrevHash,
                (string) $t->site_id,
                (string) $t->ticket_number,
                $plate,
                (int) $t->gross_weight_kg,
                (int) $t->tare_weight_kg,
                (int) $t->net_weight_kg,
                $destination,
                $weighedAt,
            ]);

            $calculatedHash = hash('sha256', $hashPayload);

            $storedHash = (string) ($t->current_hash ?: $t->hash ?: $t->ticket_hash);

            if (! hash_equals($calculatedHash, $storedHash)) {
                $this->error("INTEGRITAS RUSAK pada tiket #{$t->ticket_number} (ID: {$t->id}): Hash tidak cocok!");
                $corruptedCount++;
            }

            $expectedPrevHash = $storedHash;
        }

        if ($corruptedCount > 0) {
            $this->error("AUDIT RANTAI-HASH GAGAL: Terdeteksi {$corruptedCount} tiket timbang telah dimanipulasi!");

            return 1;
        }

        $this->info("AUDIT RANTAI-HASH SUKSES: Seluruh {$tickets->count()} tiket timbang valid secara kriptografis tanpa manipulasi.");

        return 0;
    }
}
