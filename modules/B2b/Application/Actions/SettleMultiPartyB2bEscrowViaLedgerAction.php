<?php

declare(strict_types=1);

namespace Modules\B2b\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\B2b\Domain\Models\B2bEscrowAccount;
use Modules\B2b\Ledger\B2bTransactionTypes;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

class SettleMultiPartyB2bEscrowViaLedgerAction
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * Lock escrow deposit from buyer into ledger escrow hold account.
     *
     * @param array{
     *     reference_type: string,
     *     reference_id: string,
     *     buyer_id: string,
     *     seller_id: string,
     *     deposit_amount_idr: int,
     *     escrow_number?: string
     * } $data
     */
    public function lockEscrow(array $data): B2bEscrowAccount
    {
        $deposit = (int) ($data['deposit_amount_idr'] ?? 0);
        if ($deposit <= 0) {
            throw new InvalidArgumentException('Escrow deposit amount must be a positive integer in IDR minor units.');
        }

        if (empty($data['reference_type']) || empty($data['reference_id']) || empty($data['buyer_id']) || empty($data['seller_id'])) {
            throw new InvalidArgumentException('Reference type, reference ID, buyer ID, and seller ID are required.');
        }

        return DB::transaction(function () use ($data, $deposit) {
            $escrowNumber = $data['escrow_number'] ?? 'ESC-'.strtoupper(Str::random(8));

            $escrow = B2bEscrowAccount::create([
                'escrow_number' => $escrowNumber,
                'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'],
                'buyer_id' => $data['buyer_id'],
                'seller_id' => $data['seller_id'],
                'deposit_amount_idr' => $deposit,
                'released_amount_idr' => 0,
                'refunded_amount_idr' => 0,
                'status' => 'held',
            ]);

            $this->ledgerService->post(new PostingDTO(
                type: B2bTransactionTypes::B2B_ESCROW_HOLD->value,
                description: "B2B escrow lock {$escrow->escrow_number} for {$escrow->reference_type} {$escrow->reference_id}",
                idempotencyKey: "B2B-ESCROW-HOLD-{$escrow->escrow_number}",
                entries: Posting::lines()
                    ->debit('clearing:b2b:deposit:IDR', $deposit)
                    ->credit('escrow:b2b:orders:IDR', $deposit)
                    ->toEntries('IDR'),
                referenceType: 'B2B_ESCROW',
                referenceId: (string) $escrow->id,
            ));

            return $escrow;
        });
    }

    /**
     * Settle and release escrow funds to seller upon milestone/BAST fulfillment.
     */
    public function settleAndRelease(string $escrowId, string $bastDocumentId, ?int $releaseAmountIdr = null): B2bEscrowAccount
    {
        if (empty($bastDocumentId)) {
            throw new InvalidArgumentException('BAST document verification is required to release B2B escrow.');
        }

        return DB::transaction(function () use ($escrowId, $bastDocumentId, $releaseAmountIdr) {
            $escrow = B2bEscrowAccount::where('id', $escrowId)->lockForUpdate()->firstOrFail();

            if (! in_array($escrow->status, ['held', 'partially_released'], true)) {
                throw new InvalidArgumentException("Escrow cannot be released from status '{$escrow->status}'.");
            }

            $amountToRelease = $releaseAmountIdr ?? ($escrow->deposit_amount_idr - $escrow->released_amount_idr);
            if ($amountToRelease <= 0) {
                throw new InvalidArgumentException('Release amount must be greater than zero.');
            }

            $newTotalReleased = $escrow->released_amount_idr + $amountToRelease;
            if ($newTotalReleased > $escrow->deposit_amount_idr) {
                throw new InvalidArgumentException("Total released amount ({$newTotalReleased}) exceeds deposit ({$escrow->deposit_amount_idr}).");
            }

            $newStatus = ($newTotalReleased === $escrow->deposit_amount_idr) ? 'released' : 'partially_released';

            $escrow->update([
                'released_amount_idr' => $newTotalReleased,
                'status' => $newStatus,
                'bast_document_id' => $bastDocumentId,
                'released_at' => now(),
            ]);

            $this->ledgerService->post(new PostingDTO(
                type: B2bTransactionTypes::B2B_ESCROW_RELEASE->value,
                description: "B2B escrow release {$escrow->escrow_number} tranche {$amountToRelease} IDR with BAST {$bastDocumentId}",
                idempotencyKey: "B2B-ESCROW-RELEASE-{$escrow->escrow_number}-{$newTotalReleased}",
                entries: Posting::lines()
                    ->debit('escrow:b2b:orders:IDR', $amountToRelease)
                    ->credit('ap:b2b:seller_payable:IDR', $amountToRelease)
                    ->toEntries('IDR'),
                referenceType: 'B2B_ESCROW',
                referenceId: (string) $escrow->id,
            ));

            return $escrow;
        });
    }
}
