<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;

final class AccrueCrossEcosystemPointsAction
{
    public const VALUATION_PER_PT_IDR = 100;

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    public function execute(
        string $travelPassId,
        string $originatingLine,
        int $ptsAmount,
        string $referenceCode,
        int $validityDays = 730
    ): HtlTravelPassPointBatch {
        return DB::transaction(function () use ($travelPassId, $originatingLine, $ptsAmount, $referenceCode, $validityDays) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            $liabilityIdr = $ptsAmount * self::VALUATION_PER_PT_IDR;
            $batchNumber = 'BTH-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));

            $batch = HtlTravelPassPointBatch::create([
                'id' => (string) Str::uuid(),
                'travel_pass_id' => $pass->id,
                'batch_number' => $batchNumber,
                'originating_line' => $originatingLine,
                'initial_pts' => $ptsAmount,
                'remaining_pts' => $ptsAmount,
                'liability_per_pt_idr' => self::VALUATION_PER_PT_IDR,
                'total_liability_idr' => $liabilityIdr,
                'state' => 'ACTIVE',
                'reference_code' => $referenceCode,
                'earned_at' => now(),
                'expires_at' => now()->addDays($validityDays),
            ]);

            $pass->increment('pts_balance', $ptsAmount);
            $pass->refreshTier();

            // Post PTS liability into Ledger: Debit Loyalty Expense, Credit PTS Liability
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_EARNED->value,
                description: "Cross-ecosystem PTS issuance from {$originatingLine} for pass {$pass->pass_number}",
                idempotencyKey: "HTL-PTS-ACCRUE-{$batch->id}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_program_expense:IDR', $liabilityIdr)
                    ->credit('htl:loyalty_points_liability:IDR', $liabilityIdr)
                    ->toEntries('IDR'),
                referenceType: 'PTS_BATCH',
                referenceId: $batch->id,
            ));

            return $batch;
        });
    }
}
