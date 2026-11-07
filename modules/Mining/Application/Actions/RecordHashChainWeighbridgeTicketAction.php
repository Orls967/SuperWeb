<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\WeighbridgeTicket;

class RecordHashChainWeighbridgeTicketAction
{
    /**
     * Catat tiket timbang jembatan timbang dengan bukti kriptografis rantai-hash (D94-1).
     * Mencegah fraud manipulasi tonase dan anti double-dip penimbangan berulang <= 60s (G7-D28).
     *
     * @param array{
     *     site_id: string,
     *     stockpile_id?: string,
     *     vehicle_plate: string,
     *     gross_weight_kg: int,
     *     tare_weight_kg: int,
     *     weighed_at: string,
     *     destination?: string
     * } $data
     */
    public function execute(array $data): WeighbridgeTicket
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $plate = trim((string) $data['vehicle_plate']);
            $grossKg = (int) $data['gross_weight_kg'];
            $tareKg = (int) $data['tare_weight_kg'];
            $weighedAt = CarbonImmutable::parse($data['weighed_at']);
            $netKg = $grossKg - $tareKg;

            if ($netKg <= 0) {
                throw new InvalidArgumentException("Gross weight ({$grossKg} kg) harus lebih besar dari tare weight ({$tareKg} kg).");
            }

            // 1. Anti Double-Dip Guard (G7-D28): Plat sama dalam selang <= 60 detik ditolak
            $lastTicketForPlate = WeighbridgeTicket::where(function ($q) use ($plate) {
                $q->where('vehicle_plate', $plate)
                    ->orWhere('truck_plate_number', $plate);
            })->latest('weighed_at')->first();

            if ($lastTicketForPlate && $lastTicketForPlate->weighed_at) {
                $lastTime = CarbonImmutable::parse($lastTicketForPlate->weighed_at);
                $diffSec = abs($weighedAt->getTimestamp() - $lastTime->getTimestamp());
                if ($diffSec <= 60) {
                    throw new DomainException("Dugaan kecurangan tiket ganda (double-dip): kendaraan {$plate} telah ditimbang dalam selang {$diffSec} detik (<= 60 detik).");
                }
            }

            // 2. Hash Chain Kriptografis
            $latestSiteTicket = WeighbridgeTicket::where('site_id', $siteId)
                ->latest('weighed_at')
                ->first();

            $prevHash = $latestSiteTicket?->current_hash ?? $latestSiteTicket?->hash ?? 'GENESIS_WEIGHBRIDGE_HASH';
            $ticketNumber = 'WB-'.strtoupper(Str::random(10));
            $destination = $data['destination'] ?? 'STOCKPILE_IN';

            $hashPayload = implode('|', [
                $prevHash,
                $siteId,
                $ticketNumber,
                $plate,
                $grossKg,
                $tareKg,
                $netKg,
                $destination,
                $weighedAt->toIso8601String(),
            ]);
            $currentHash = hash('sha256', $hashPayload);

            // 3. Simpan Tiket
            $ticket = WeighbridgeTicket::create([
                'id' => (string) Str::uuid(),
                'ticket_uuid' => (string) Str::uuid(),
                'site_id' => $siteId,
                'stockpile_id' => $data['stockpile_id'] ?? null,
                'destination_stockpile_id' => $data['stockpile_id'] ?? null,
                'destination_stockpile' => $data['stockpile_id'] ?? 'STOCKPILE-MAIN',
                'ticket_number' => $ticketNumber,
                'truck_plate_number' => $plate,
                'vehicle_plate' => $plate,
                'gross_weight_kg' => $grossKg,
                'tare_weight_kg' => $tareKg,
                'net_weight_kg' => $netKg,
                'gross_weight_ton' => round($grossKg / 1000, 2),
                'tare_weight_ton' => round($tareKg / 1000, 2),
                'net_weight_ton' => round($netKg / 1000, 2),
                'nickel_grade_percentage' => (float) ($data['nickel_grade_percentage'] ?? 0.0),
                'hash' => $currentHash,
                'current_hash' => $currentHash,
                'previous_hash' => $prevHash,
                'prev_hash' => $prevHash,
                'ticket_hash' => $currentHash,
                'destination' => $destination,
                'weighed_at' => $weighedAt,
            ]);

            // 4. Perbarui tonase stockpile bila ada
            if (! empty($data['stockpile_id'])) {
                $stockpile = MiningStockpile::find($data['stockpile_id']);
                if ($stockpile) {
                    if ($destination === 'STOCKPILE_IN') {
                        $stockpile->total_kg += $netKg;
                    } elseif ($destination === 'STOCKPILE_OUT') {
                        $stockpile->total_kg = max(0, $stockpile->total_kg - $netKg);
                    }
                    $stockpile->save();
                }
            }

            return $ticket;
        });
    }
}
