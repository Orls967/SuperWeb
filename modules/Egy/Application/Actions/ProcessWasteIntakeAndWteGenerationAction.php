<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\WasteIntake;
use Modules\Egy\Domain\Models\WasteTreatmentFacility;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Application\BaseAction;

final class ProcessWasteIntakeAndWteGenerationAction extends BaseAction
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function execute(
        int $facilityId,
        int $tonnageKg,
        string $wasteType,
        int $tippingFeeRateIdrPerTon,
        string $idempotencyKey
    ): WasteIntake {
        if ($facilityId <= 0 || $tonnageKg <= 0 || $tippingFeeRateIdrPerTon < 0) {
            throw new InvalidArgumentException('Parameter fasilitas limbah dan tonase tidak valid');
        }

        return DB::transaction(function () use (
            $facilityId,
            $tonnageKg,
            $wasteType,
            $tippingFeeRateIdrPerTon,
            $idempotencyKey
        ) {
            $facility = WasteTreatmentFacility::findOrFail($facilityId);

            $tons = (int) intdiv($tonnageKg, 1000);
            $totalTippingFee = $tons * $tippingFeeRateIdrPerTon;
            $projectedBiogasM3 = $tons * $facility->biogas_yield_m3_per_ton;

            $intake = WasteIntake::create([
                'facility_id' => $facility->id,
                'vehicle_number' => 'B-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 4)).'-WTE',
                'waste_type' => $wasteType,
                'tonnage_kg' => $tonnageKg,
                'tipping_fee_rate_idr_per_ton' => $tippingFeeRateIdrPerTon,
                'total_tipping_fee_idr' => $totalTippingFee,
                'projected_biogas_m3' => $projectedBiogasM3,
                'recorded_at' => Carbon::now(),
                'status' => 'settled',
            ]);

            // Posting Ledger Tipping Fee (D127-6)
            EgyAccounts::ensureAccount('ar:egy:government:IDR', 'AR Government Tipping Fee', AccountKind::ASSET, 'IDR', true);
            EgyAccounts::ensureAccount('egy:tipping_fee_revenue:IDR', 'Waste Tipping Fee Revenue', AccountKind::REVENUE, 'IDR', false);

            $postingLines = Posting::lines()
                ->debit('ar:egy:government:IDR', $totalTippingFee)
                ->credit('egy:tipping_fee_revenue:IDR', $totalTippingFee);

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: "Waste tipping fee intake #{$intake->id} ({$tons} tons)",
                idempotencyKey: "POST-{$idempotencyKey}",
                entries: $postingLines->toEntries('IDR'),
                referenceType: 'WASTE_INTAKE',
                referenceId: (string) $intake->id
            ));

            $intake->update(['ledger_transaction_id' => (int) $tx->id]);

            return $intake;
        });
    }
}
