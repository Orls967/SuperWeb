<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\Domain\Models\MarketBroker;
use Modules\Party\Contracts\Party;

final class RegisterBrokerAction
{
    public function execute(
        string $partyId,
        string $licenseNumber,
        string $tier = 'silver',
        int $commissionRateBps = 1000,
    ): MarketBroker {
        return DB::transaction(function () use ($partyId, $licenseNumber, $tier, $commissionRateBps) {
            $party = Party::query()->find($partyId);
            if ($party === null) {
                throw new DomainException("Party {$partyId} tidak ditemukan.");
            }

            $existing = MarketBroker::query()->where('party_id', $partyId)->first();
            if ($existing !== null) {
                return $existing;
            }

            return MarketBroker::create([
                'party_id' => $partyId,
                'license_number' => $licenseNumber,
                'tier' => $tier,
                'commission_rate_bps' => $commissionRateBps,
                'rating_score' => 85,
                'status' => 'active',
            ]);
        });
    }
}
