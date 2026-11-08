<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Algorithms\PointsLiabilityFifo;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelEsgCarbonOffsetDonationService
{
    public const KG_CO2_PER_POINT = 2.5; // 1 PTS offsets 2.5 kg CO2

    public const COST_PER_KG_CO2_IDR = 40; // Rp 100 per point = 2.5 kg * Rp 40

    public function __construct(
        protected ?PointsLiabilityFifo $fifo = null,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->fifo = $fifo ?? new PointsLiabilityFifo;
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     certificate_id: string,
     *     travel_pass_id: string,
     *     program_code: string,
     *     points_donated: int,
     *     kg_co2_offset: float,
     *     donation_value_idr: int,
     *     carbon_registry_serial: string,
     *     issued_at: string
     * }
     */
    public function donatePointsForCarbonOffset(
        string $travelPassId,
        int $pointsToDonate,
        string $esgProgramCode = 'MANGROVE_REFORESTATION_BALI',
        string $treePlantingLocation = 'Taman Hutan Raya Ngurah Rai Bali'
    ): array {
        if ($pointsToDonate < 100) {
            throw new RuntimeException("Minimal donasi carbon offset adalah 100 PTS (diberikan: {$pointsToDonate} PTS)");
        }

        return DB::transaction(function () use (
            $travelPassId,
            $pointsToDonate,
            $esgProgramCode,
            $treePlantingLocation
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            if ($pass->pts_balance < $pointsToDonate) {
                throw new RuntimeException("Saldo poin tidak mencukupi untuk donasi ESG: Saldo {$pass->pts_balance} PTS, diminta {$pointsToDonate} PTS");
            }

            // Fetch FIFO batches
            $batches = HtlTravelPassPointBatch::where('travel_pass_id', $pass->id)
                ->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
                ->lockForUpdate()
                ->get();

            $batchList = [];
            foreach ($batches as $b) {
                $batchList[] = [
                    'batch_id' => $b->id,
                    'remaining_pts' => (int) $b->remaining_pts,
                    'liability_per_pt_idr' => (int) $b->liability_per_pt_idr,
                    'expires_at_timestamp' => $b->expires_at->getTimestamp(),
                ];
            }

            $fifoCert = $this->fifo->allocate(
                batches: $batchList,
                ptsToRedeem: $pointsToDonate,
                currentTimestamp: now()->getTimestamp()
            );

            // Deduct from batches
            foreach ($fifoCert->redemptionAllocations as $alloc) {
                $bModel = $batches->firstWhere('id', $alloc['batch_id']);
                if ($bModel) {
                    $newRem = $bModel->remaining_pts - $alloc['deducted_pts'];
                    $bModel->update([
                        'remaining_pts' => $newRem,
                        'state' => $newRem === 0 ? 'FULLY_REDEEMED' : 'PARTIALLY_REDEEMED',
                    ]);
                }
            }

            // Deduct pass
            $pass->decrement('pts_balance', $pointsToDonate);

            $kgCo2Offset = round($pointsToDonate * self::KG_CO2_PER_POINT, 2);
            $donationValueIdr = $pointsToDonate * 100;
            $certificateId = 'ESG-VCS-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
            $registrySerial = 'VCS-VNU-'.now()->format('Y').'-'.mt_rand(100000, 999999);

            // Post to ledger: Debit Loyalty Points Liability, Credit ESG Carbon Clearing Payable
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_REDEEMED->value,
                description: "ESG Carbon Offset: {$pointsToDonate} PTS ({$kgCo2Offset} kg CO2) for {$esgProgramCode} in {$treePlantingLocation}",
                idempotencyKey: "HTL-ESG-DONATE-{$certificateId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_points_liability:IDR', $donationValueIdr)
                    ->credit('clearing:external:IDR', $donationValueIdr)
                    ->toEntries('IDR'),
                referenceType: 'ESG_CARBON_CERTIFICATE',
                referenceId: $certificateId,
            ));

            return [
                'certificate_id' => $certificateId,
                'travel_pass_id' => $pass->id,
                'program_code' => $esgProgramCode,
                'points_donated' => $pointsToDonate,
                'kg_co2_offset' => $kgCo2Offset,
                'donation_value_idr' => $donationValueIdr,
                'carbon_registry_serial' => $registrySerial,
                'issued_at' => now()->toIso8601String(),
            ];
        });
    }
}
