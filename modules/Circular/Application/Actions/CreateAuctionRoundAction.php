<?php

declare(strict_types=1);

namespace Modules\Circular\Application\Actions;

use App\Models\User;
use Modules\Circular\Domain\Models\CirAuctionRound;

final class CreateAuctionRoundAction
{
    /**
     * @param array{
     *     material_code: string,
     *     grade_code?: string,
     *     origin_location_id?: int|null,
     *     destination_facility_id?: int|null,
     *     idempotency_key?: string|null
     * } $data
     */
    public function execute(array $data, ?User $actor = null): CirAuctionRound
    {
        if (! empty($data['idempotency_key'])) {
            $existing = CirAuctionRound::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $grade = $data['grade_code'] ?? 'A';
        $originLocationId = $data['origin_location_id'] ?? null;
        $destinationFacilityId = $data['destination_facility_id'] ?? $originLocationId;
        $seed = hash('sha256', "CIR-RND:{$data['material_code']}:{$grade}:".microtime(true));
        $roundCode = 'CIR-RND-'.strtoupper(substr($seed, 0, 10));

        return CirAuctionRound::create([
            'round_code' => $roundCode,
            'material_code' => $data['material_code'],
            'grade_code' => $grade,
            'origin_location_id' => $originLocationId,
            'destination_facility_id' => $destinationFacilityId,
            'status' => 'open',
            'clearing_price_per_kg_idr' => null,
            'cleared_volume_grams' => 0,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }
}
