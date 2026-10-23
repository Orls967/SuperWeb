<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\CargoQualityDispute;
use Modules\Mining\Domain\Models\VesselVoyage;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class ScheduleBargeTransshipmentAndExportDemurrageAction extends BaseAction
{
    /**
     * Schedule export vessel voyage, screen trade sanctions, calculate demurrage, and manage assay grade disputes.
     *
     * @return array{voyage: VesselVoyage, dispute: ?CargoQualityDispute, demurrage_total_idr: int, is_disputed: bool}
     */
    public function handle(
        string $vesselName,
        string $destinationCountry,
        float $loadedTonnage = 55000.0,
        float $allowedLaytimeHours = 72.0,
        float $actualLaytimeHours = 96.0,
        int $demurrageRatePerHourIdr = 25000000,
        float $sellerGradePct = 1.80,
        float $surveyorGradePct = 1.68,
        float $gradeTolerancePct = 0.05
    ): array {
        return DB::transaction(function () use (
            $vesselName,
            $destinationCountry,
            $loadedTonnage,
            $allowedLaytimeHours,
            $actualLaytimeHours,
            $demurrageRatePerHourIdr,
            $sellerGradePct,
            $surveyorGradePct,
            $gradeTolerancePct
        ) {
            // 122.5 International trade sanctions compliance screening
            $sanctionedDestinations = ['SANCTIONED_COUNTRY', 'NORTH_KOREA', 'EMBARGOED_STATE'];
            if (in_array(strtoupper($destinationCountry), $sanctionedDestinations, true)) {
                throw new RuntimeException("Export blocked: Destination country {$destinationCountry} is under international trade sanctions embargo");
            }

            // 122.2 Laytime & Demurrage calculation
            $exceededHours = max(0.0, $actualLaytimeHours - $allowedLaytimeHours);
            $demurrageTotalIdr = (int) round($exceededHours * $demurrageRatePerHourIdr);

            $voyageCount = VesselVoyage::count() + 1;
            $voyageNumber = sprintf('VYG-%06d', $voyageCount);
            $bolHash = hash('sha256', "BOL-{$voyageNumber}-{$vesselName}-{$loadedTonnage}-".date('YmdHis'));

            $voyage = VesselVoyage::create([
                'id' => (string) Str::uuid(),
                'voyage_number' => $voyageNumber,
                'vessel_name' => $vesselName,
                'buyer_party_id' => 'BUYER-GLENCORE-01',
                'destination_country' => $destinationCountry,
                'contracted_tonnage' => $loadedTonnage,
                'loaded_tonnage' => $loadedTonnage,
                'bill_of_lading_hash' => $bolHash,
                'allowed_laytime_hours' => $allowedLaytimeHours,
                'actual_laytime_hours' => $actualLaytimeHours,
                'demurrage_rate_per_hour_minor' => $demurrageRatePerHourIdr,
                'demurrage_total_minor' => $demurrageTotalIdr,
                'sanctions_blocked' => false,
                'status' => 'DISPATCHED',
            ]);

            // 122.3 Assay quality dispute
            $dispute = null;
            $gradeVariance = abs($sellerGradePct - $surveyorGradePct);
            $isDisputed = ($gradeVariance > $gradeTolerancePct);

            if ($isDisputed) {
                $disputeCount = CargoQualityDispute::count() + 1;
                $disputeNumber = sprintf('DSP-%06d', $disputeCount);

                $dispute = CargoQualityDispute::create([
                    'id' => (string) Str::uuid(),
                    'dispute_number' => $disputeNumber,
                    'voyage_id' => $voyage->id,
                    'surveyor_party_id' => 'SURVEYOR-SGS-01',
                    'seller_grade_pct' => $sellerGradePct,
                    'buyer_grade_pct' => $surveyorGradePct,
                    'grade_tolerance_pct' => $gradeTolerancePct,
                    'sealed_sample_code' => 'SEAL-'.strtoupper(bin2hex(random_bytes(4))),
                    'is_payment_held' => true,
                    'status' => 'DISPUTED',
                ]);
            }

            $this->audit(
                action: 'mining.export_voyage.scheduled',
                auditable: $voyage,
                oldValues: null,
                newValues: [
                    'voyage_number' => $voyageNumber,
                    'vessel_name' => $vesselName,
                    'destination_country' => $destinationCountry,
                    'demurrage_total_idr' => $demurrageTotalIdr,
                    'is_disputed' => $isDisputed,
                ],
                correlationId: "min_voyage_{$voyageNumber}",
                impactType: 'export_logistics_demurrage'
            );

            return [
                'voyage' => $voyage,
                'dispute' => $dispute,
                'demurrage_total_idr' => $demurrageTotalIdr,
                'is_disputed' => $isDisputed,
            ];
        });
    }
}
