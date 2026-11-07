<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelDeceasedMemberPointsTransferService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @param  list<string>  $courtDocumentReferences
     * @return array{
     *     transfer_id: string,
     *     deceased_pass_id: string,
     *     beneficiary_pass_id: string,
     *     transferred_points: int,
     *     batches_reassigned: int,
     *     status: string,
     *     completed_at: string
     * }
     */
    public function transferDeceasedPointsToBeneficiary(
        string $deceasedPassId,
        string $beneficiaryPassId,
        string $probateGrantNumber,
        array $courtDocumentReferences,
        string $approvedByStaffReference
    ): array {
        if ($deceasedPassId === $beneficiaryPassId) {
            throw new RuntimeException('Akun pewaris dan ahli waris tidak boleh sama.');
        }

        if (empty($probateGrantNumber)) {
            throw new RuntimeException('Nomor penetapan waris atau akta kematian pengadilan wajib dilampirkan.');
        }

        return DB::transaction(function () use (
            $deceasedPassId,
            $beneficiaryPassId,
            $probateGrantNumber,
            $courtDocumentReferences,
            $approvedByStaffReference
        ) {
            $deceased = HtlTravelPass::where('id', $deceasedPassId)->lockForUpdate()->firstOrFail();
            $beneficiary = HtlTravelPass::where('id', $beneficiaryPassId)->lockForUpdate()->firstOrFail();

            $pointsToTransfer = (int) $deceased->pts_balance;
            if ($pointsToTransfer <= 0) {
                throw new RuntimeException('Akun almarhum tidak memiliki sisa saldo poin untuk diwariskan.');
            }

            // Fetch active batches of deceased member
            $batches = HtlTravelPassPointBatch::where('travel_pass_id', $deceased->id)
                ->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
                ->lockForUpdate()
                ->get();

            $batchesReassigned = 0;
            foreach ($batches as $batch) {
                // Extend expiration by 1 year from bereavement transfer date
                $batch->update([
                    'travel_pass_id' => $beneficiary->id,
                    'reference_code' => "BEREAVEMENT-INHERIT-{$batch->reference_code}",
                    'expires_at' => now()->addYear(),
                ]);
                $batchesReassigned++;
            }

            // Zero out deceased account and mark deactivated
            $deceased->update([
                'pts_balance' => 0,
                'is_active' => false,
                'metadata' => array_merge($deceased->metadata ?? [], [
                    'deactivation_reason' => 'DECEASED_ESTATE_SETTLED',
                    'probate_grant_number' => $probateGrantNumber,
                    'transferred_to_pass_id' => $beneficiary->id,
                    'processed_by_staff' => $approvedByStaffReference,
                    'court_documents' => $courtDocumentReferences,
                    'settled_at' => now()->toIso8601String(),
                ]),
            ]);

            // Credit beneficiary pass
            $beneficiary->increment('pts_balance', $pointsToTransfer);
            $beneficiary->refreshTier();

            $transferId = 'BEREAVEMENT-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4));

            // Internal ledger realignment (Point liability re-tagging)
            $liabilityValueIdr = $pointsToTransfer * 100;
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_EARNED->value,
                description: "Bereavement inheritance transfer: {$pointsToTransfer} PTS from {$deceased->pass_number} to {$beneficiary->pass_number} under grant {$probateGrantNumber}",
                idempotencyKey: "HTL-BEREAVEMENT-{$transferId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_points_liability:IDR', $liabilityValueIdr)
                    ->credit('htl:loyalty_points_liability:IDR', $liabilityValueIdr)
                    ->toEntries('IDR'),
                referenceType: 'BEREAVEMENT_TRANSFER',
                referenceId: $transferId,
            ));

            return [
                'transfer_id' => $transferId,
                'deceased_pass_id' => $deceased->id,
                'beneficiary_pass_id' => $beneficiary->id,
                'transferred_points' => $pointsToTransfer,
                'batches_reassigned' => $batchesReassigned,
                'status' => 'COMPLETED',
                'completed_at' => now()->toIso8601String(),
            ];
        });
    }
}
