<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Shared\Application\BaseAction;

class RecordWeighbridgeHashChainTicketAction extends BaseAction
{
    /**
     * Record haulage weighbridge ticket with tamper-evident cryptographic hash-chaining.
     *
     * @return array{ticket: WeighbridgeTicket, ticket_number: string, net_weight_ton: float, hash: string}
     */
    public function handle(
        string $siteId,
        string $truckPlateNumber,
        float $grossWeightTon,
        float $tareWeightTon,
        float $nickelGradePercentage,
        string $destinationStockpile
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $truckPlateNumber,
            $grossWeightTon,
            $tareWeightTon,
            $nickelGradePercentage,
            $destinationStockpile
        ) {
            $netWeightTon = round($grossWeightTon - $tareWeightTon, 2);

            if ($netWeightTon <= 0) {
                throw new \InvalidArgumentException('Gross weight must exceed tare weight.');
            }

            // Fetch previous ticket for this site to maintain tamper-evident hash chain
            $latest = WeighbridgeTicket::where('site_id', $siteId)
                ->latest('created_at')
                ->first();

            $previousHash = $latest?->hash;
            $ticketNumber = 'WB-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $payload = "{$siteId}_{$ticketNumber}_{$truckPlateNumber}_{$netWeightTon}_{$nickelGradePercentage}_{$previousHash}";
            $currentHash = hash('sha256', $payload);

            $ticket = WeighbridgeTicket::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'ticket_number' => $ticketNumber,
                'truck_plate_number' => $truckPlateNumber,
                'gross_weight_ton' => $grossWeightTon,
                'tare_weight_ton' => $tareWeightTon,
                'net_weight_ton' => $netWeightTon,
                'nickel_grade_percentage' => $nickelGradePercentage,
                'destination_stockpile' => $destinationStockpile,
                'hash' => $currentHash,
                'previous_hash' => $previousHash,
            ]);

            $this->audit(
                action: 'mining.weighbridge.ticket_recorded',
                auditable: $ticket,
                oldValues: null,
                newValues: [
                    'ticket_number' => $ticketNumber,
                    'truck' => $truckPlateNumber,
                    'net_weight_ton' => $netWeightTon,
                    'grade_ni_pct' => $nickelGradePercentage,
                    'hash' => $currentHash,
                ],
                correlationId: "min_wb_{$ticketNumber}",
                impactType: 'weighbridge_custody'
            );

            return [
                'ticket' => $ticket,
                'ticket_number' => $ticketNumber,
                'net_weight_ton' => $netWeightTon,
                'hash' => $currentHash,
            ];
        });
    }
}
