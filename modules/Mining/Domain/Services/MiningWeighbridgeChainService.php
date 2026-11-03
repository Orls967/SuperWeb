<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Shared\Domain\Algorithms\Chain\CanonicalHashChain;

class MiningWeighbridgeChainService
{
    private readonly CanonicalHashChain $chain;

    public function __construct(?CanonicalHashChain $chain = null)
    {
        $this->chain = $chain ?? new CanonicalHashChain;
    }

    /**
     * @param  array<string, mixed>  $specs
     * @return array{prev_hash: ?string, ticket_hash: string}
     */
    public function computeTicketHash(
        string $siteId,
        string $ticketNumber,
        string $truckPlateNumber,
        int $netWeightKg,
        array $specs = [],
        ?string $previousHash = null
    ): array {
        $payload = [
            'site_id' => $siteId,
            'ticket_number' => $ticketNumber,
            'truck' => $truckPlateNumber,
            'net_weight_kg' => $netWeightKg,
            'specs' => $specs,
        ];

        $prev = $previousHash ?? CanonicalHashChain::GENESIS_HASH;
        $currentHash = $this->chain->link($prev, $payload);

        return [
            'prev_hash' => $previousHash,
            'ticket_hash' => $currentHash,
        ];
    }

    public function verifySiteChain(string $siteId): bool
    {
        $tickets = WeighbridgeTicket::where('site_id', $siteId)
            ->orderBy('weighed_at')
            ->orderBy('ticket_number')
            ->orderBy('id')
            ->get();
        if ($tickets->isEmpty()) {
            return true;
        }

        $expectedPrev = null;

        foreach ($tickets as $ticket) {
            if ($ticket->prev_hash !== $expectedPrev) {
                return false;
            }

            $payload = [
                'site_id' => $siteId,
                'ticket_number' => $ticket->ticket_number,
                'truck' => $ticket->truck_plate_number,
                'net_weight_kg' => (int) $ticket->net_weight_kg,
                'specs' => is_array($ticket->grade_specs) ? $ticket->grade_specs : [],
            ];

            $prev = $ticket->prev_hash ?? CanonicalHashChain::GENESIS_HASH;
            $expectedHash = $this->chain->link($prev, $payload);

            if (! hash_equals($expectedHash, (string) ($ticket->ticket_hash ?? $ticket->hash))) {
                return false;
            }

            $expectedPrev = $ticket->ticket_hash ?? $ticket->hash;
        }

        return true;
    }
}
