<?php

declare(strict_types=1);

namespace Modules\Circular\Application\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Circular\Domain\Models\CirAuctionRound;
use Modules\Circular\Domain\Models\CirBid;

final class SubmitBidAction
{
    /**
     * @param array{
     *     auction_round_id: int,
     *     buyer_party_id: int,
     *     quantity_grams: int,
     *     max_price_per_kg_idr: int,
     *     idempotency_key?: string|null
     * } $data
     */
    public function execute(array $data, ?User $actor = null): CirBid
    {
        if (! empty($data['idempotency_key'])) {
            $existing = CirBid::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $round = CirAuctionRound::findOrFail($data['auction_round_id']);
        if ($round->status !== 'open') {
            throw ValidationException::withMessages([
                'auction_round_id' => 'Ronde lelang sudah ditutup dan tidak menerima penawaran baru.',
            ]);
        }

        if ($data['quantity_grams'] <= 0) {
            throw ValidationException::withMessages([
                'quantity_grams' => 'Kuantitas bid harus lebih besar dari 0 gram.',
            ]);
        }

        if ($data['max_price_per_kg_idr'] <= 0) {
            throw ValidationException::withMessages([
                'max_price_per_kg_idr' => 'Harga maksimum bid harus lebih besar dari Rp 0.',
            ]);
        }

        return CirBid::create([
            'auction_round_id' => $round->id,
            'buyer_party_id' => $data['buyer_party_id'],
            'quantity_grams' => $data['quantity_grams'],
            'max_price_per_kg_idr' => $data['max_price_per_kg_idr'],
            'allocated_grams' => 0,
            'status' => 'active',
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }
}
