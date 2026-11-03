<?php

declare(strict_types=1);

namespace Modules\Circular\Application\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Circular\Domain\Models\CirListing;

final class CreateListingAction
{
    /**
     * @param array{
     *     seller_party_id: int,
     *     auction_round_id?: int|null,
     *     material_code: string,
     *     grade_code?: string,
     *     available_grams: int,
     *     unit_price_per_kg_idr: int,
     *     mode?: string,
     *     origin_location_id?: int|null,
     *     idempotency_key?: string|null
     * } $data
     */
    public function execute(array $data, ?User $actor = null): CirListing
    {
        if ($data['available_grams'] <= 0) {
            throw ValidationException::withMessages([
                'available_grams' => 'Kuantitas material ditawarkan harus lebih besar dari 0 gram.',
            ]);
        }

        if ($data['unit_price_per_kg_idr'] <= 0) {
            throw ValidationException::withMessages([
                'unit_price_per_kg_idr' => 'Harga satuan per kg harus lebih besar dari Rp 0.',
            ]);
        }

        if (! empty($data['idempotency_key'])) {
            $existing = CirListing::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $originLocationId = $data['origin_location_id'] ?? null;
        $mode = $data['mode'] ?? 'fixed';
        $grade = $data['grade_code'] ?? 'A';
        $seed = hash('sha256', "CIR-LST:{$data['seller_party_id']}:{$data['material_code']}:{$grade}:".microtime(true));
        $listingCode = 'CIR-LST-'.strtoupper(substr($seed, 0, 10));

        return CirListing::create([
            'listing_code' => $listingCode,
            'seller_party_id' => $data['seller_party_id'],
            'auction_round_id' => $data['auction_round_id'] ?? null,
            'material_code' => $data['material_code'],
            'grade_code' => $grade,
            'available_grams' => $data['available_grams'],
            'unit_price_per_kg_idr' => $data['unit_price_per_kg_idr'],
            'mode' => $mode,
            'status' => 'active',
            'origin_location_id' => $originLocationId,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }
}
