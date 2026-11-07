<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class HotelTimeshareSecondaryEscrowExchangeService
{
    /**
     * Memfasilitasi pertukaran tripartite (tiga pihak) atau pertukaran bersyarat dengan escrow balancing fee.
     *
     * @return array{escrow_swap_id: string, requester_id: string, target_id: string, escrow_amount_idr: int, escrow_status: string, expires_at: string}
     */
    public function lockEscrowForExchange(
        string $requesterOwnerId,
        string $targetOwnerId,
        int $balancingFeeIdr,
        int $validityHours = 48
    ): array {
        if ($balancingFeeIdr < 0) {
            throw new InvalidArgumentException('Balancing fee escrow tidak boleh bernilai negatif.');
        }

        $swapId = 'SWP-ESC-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $expiresAt = CarbonImmutable::now()->addHours($validityHours)->toIso8601String();

        return [
            'escrow_swap_id' => $swapId,
            'requester_id' => $requesterOwnerId,
            'target_id' => $targetOwnerId,
            'escrow_amount_idr' => $balancingFeeIdr,
            'escrow_status' => 'HELD_PENDING_CONFIRMATION',
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Menyelesaikan pertukaran dan melepaskan dana escrow balancing fee ke pemilik vila tujuan.
     */
    public function releaseExchangeEscrow(string $escrowSwapId, string $targetOwnerId, int $feeAmountIdr): array
    {
        return [
            'escrow_swap_id' => $escrowSwapId,
            'recipient_owner_id' => $targetOwnerId,
            'amount_released_idr' => $feeAmountIdr,
            'ledger_debit' => 'htl:escrow:timeshare_swap:IDR',
            'ledger_credit' => "investor:dividend:{$targetOwnerId}:IDR",
            'status' => 'ESCROW_RELEASED_SETTLED',
            'released_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
