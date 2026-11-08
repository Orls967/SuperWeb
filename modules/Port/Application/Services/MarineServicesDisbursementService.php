<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Port\Contracts\VesselCallWindowProvider;
use Modules\Port\Domain\Models\FleetBerthBooking;
use Modules\Port\Domain\Models\FleetBunkeringOperation;
use Modules\Port\Domain\Models\FleetPilotageRequest;
use Modules\Port\Domain\Models\FleetPortCall;
use Modules\Port\Domain\Models\FleetPortDisbursement;
use Modules\Port\Domain\Models\FleetTugService;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetWasteDisposal;

/**
 * Service Layanan Maritim Pelabuhan, Jendela Tambat & Akun Pengeluaran (PDA/FDA).
 * Mengimplementasikan kontrak G7-K27 (VesselCallWindowProvider).
 */
final class MarineServicesDisbursementService implements VesselCallWindowProvider
{
    /**
     * Daftarkan port call baru di pelabuhan.
     */
    public function schedulePortCall(
        int $voyageId,
        string $portCode,
        string $portName,
        string $purpose,
        CarbonImmutable $eta,
        float $arrDraftFwd = 9.50,
        float $arrDraftAft = 10.20
    ): FleetPortCall {
        return FleetPortCall::create([
            'voyage_id' => $voyageId,
            'port_code' => $portCode,
            'port_name' => $portName,
            'call_purpose' => $purpose,
            'estimated_arrival_at' => $eta->toDateTimeString(),
            'arrival_draft_forward_m' => $arrDraftFwd,
            'arrival_draft_aft_m' => $arrDraftAft,
            'departure_draft_forward_m' => $arrDraftFwd + 0.5,
            'departure_draft_aft_m' => $arrDraftAft + 0.5,
            'call_status' => 'SCHEDULED',
        ]);
    }

    // =========================================================================
    // Implementasi Kontrak G7-K27 (VesselCallWindowProvider)
    // =========================================================================

    public function findAvailableBerthWindow(
        string $portCode,
        CarbonImmutable $estimatedArrival,
        CarbonImmutable $estimatedDeparture,
        float $lengthOverallM,
        float $arrivalDraftM
    ): ?array {
        // Alokasikan dermaga yang memenuhi panjang kapal dan draft sarat air
        $berthPrefix = strtoupper(substr($portCode, -3));
        $assignedBerth = "BERTH-{$berthPrefix}-01";
        $maxDraft = 14.50;

        if ($arrivalDraftM > $maxDraft) {
            return null; // Draft kapal melampaui kedalaman kolam dermaga
        }

        // Cek bentrok booking yang beririsan
        $collision = FleetBerthBooking::where('berth_code', $assignedBerth)
            ->where('booking_status', 'CONFIRMED')
            ->where(function ($q) use ($estimatedArrival, $estimatedDeparture) {
                $q->whereBetween('window_start_at', [$estimatedArrival, $estimatedDeparture])
                    ->orWhereBetween('window_end_at', [$estimatedArrival, $estimatedDeparture]);
            })
            ->exists();

        if ($collision) {
            $assignedBerth = "BERTH-{$berthPrefix}-02";
        }

        return [
            'berth_code' => $assignedBerth,
            'window_start' => $estimatedArrival->toIso8601String(),
            'window_end' => $estimatedDeparture->toIso8601String(),
            'max_draft_m' => $maxDraft,
            'allocated_length_m' => round($lengthOverallM * 1.15, 2), // safety margin 15%
            'is_available' => true,
        ];
    }

    public function reserveCallWindow(
        int $portCallId,
        string $berthCode,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        float $allocatedLengthM,
        float $maxDraftM
    ): array {
        $booking = FleetBerthBooking::create([
            'port_call_id' => $portCallId,
            'berth_code' => $berthCode,
            'window_start_at' => $windowStart->toDateTimeString(),
            'window_end_at' => $windowEnd->toDateTimeString(),
            'allocated_length_m' => $allocatedLengthM,
            'max_draft_m' => $maxDraftM,
            'booking_status' => 'CONFIRMED',
        ]);

        return [
            'booking_id' => (int) $booking->id,
            'port_call_id' => $portCallId,
            'berth_code' => $berthCode,
            'booking_status' => 'CONFIRMED',
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $windowEnd->toIso8601String(),
        ];
    }

    public function releaseCallWindow(int $bookingId, string $cancellationReason): bool
    {
        $booking = FleetBerthBooking::find($bookingId);
        if (! $booking) {
            return false;
        }

        $booking->update(['booking_status' => 'CANCELLED']);

        return true;
    }

    // =========================================================================
    // Layanan Maritim: Pandu, Tunda, Bunkering, dan PDA/FDA
    // =========================================================================

    public function requestPilotage(
        int $portCallId,
        string $serviceType,
        float $feeUsd = 1200.0,
        ?string $pilotName = 'Capt. Suryo'
    ): FleetPilotageRequest {
        return FleetPilotageRequest::create([
            'port_call_id' => $portCallId,
            'service_type' => $serviceType,
            'pilot_name' => $pilotName,
            'pilotage_fee_usd' => $feeUsd,
            'status' => 'REQUESTED',
        ]);
    }

    public function assignTugboat(
        int $portCallId,
        string $tugName,
        int $bollardPullTonnage,
        int $hoursUsed,
        float $hourlyRate = 650.0,
        string $phase = 'BERTHING'
    ): FleetTugService {
        $totalCost = $hoursUsed * $hourlyRate;

        return FleetTugService::create([
            'port_call_id' => $portCallId,
            'tug_name' => $tugName,
            'bollard_pull_tonnage' => $bollardPullTonnage,
            'hours_used' => $hoursUsed,
            'hourly_rate_usd' => $hourlyRate,
            'total_cost_usd' => round($totalCost, 2),
            'service_phase' => $phase,
        ]);
    }

    public function recordBunkeringOperation(
        int $vesselId,
        ?int $portCallId,
        string $stemNumber,
        string $supplier,
        string $barge,
        string $fuelGrade,
        float $quantityMt,
        float $density = 991.0,
        float $sulphur = 0.48,
        float $viscosity = 380.0,
        ?string $bdnNumber = null,
        ?string $marpolSeal = null
    ): FleetBunkeringOperation {
        $vessel = FleetVessel::findOrFail($vesselId);

        // Standar MARPOL Annex VI: Batas sulphur global max 0.50% untuk VLSFO, max 0.10% untuk LSMGO di SECA
        $isOffSpec = ($fuelGrade === 'VLSFO' && $sulphur > 0.50) || ($fuelGrade === 'LSMGO' && $sulphur > 0.10);

        $op = FleetBunkeringOperation::create([
            'vessel_id' => $vessel->id,
            'port_call_id' => $portCallId,
            'bunker_stem_number' => $stemNumber,
            'supplier_name' => $supplier,
            'barge_name' => $barge,
            'fuel_grade' => $fuelGrade,
            'quantity_metric_tons' => $quantityMt,
            'density_at_15c_kg_m3' => $density,
            'sulphur_content_percentage' => $sulphur,
            'viscosity_cst_at_50c' => $viscosity,
            'bunker_delivery_note_number' => $bdnNumber ?? ('BDN-'.strtoupper(bin2hex(random_bytes(4)))),
            'marpol_seal_number' => $marpolSeal ?? ('SEAL-MARPOL-'.strtoupper(bin2hex(random_bytes(3)))),
            'bunkering_started_at' => CarbonImmutable::now()->subHours(4)->toDateTimeString(),
            'bunkering_completed_at' => CarbonImmutable::now()->toDateTimeString(),
            'compliance_status' => $isOffSpec ? 'OFF_SPEC' : 'COMPLIANT',
        ]);

        // Perbarui sisa bunker ROB di tangki kapal jika compliant
        if (! $isOffSpec) {
            $tank = $vessel->bunkerTanks()->where('fuel_grade', $fuelGrade)->first();
            if ($tank) {
                $tank->increment('current_rob_metric_tons', $quantityMt);
            }
        }

        return $op;
    }

    public function generateProformaDisbursement(
        int $portCallId,
        string $accountRef,
        float $portDues,
        float $pilotage,
        float $towage,
        float $berthHire,
        float $linesmen = 450.0,
        float $wasteReception = 600.0,
        float $agencyFee = 2500.0,
        float $customs = 800.0
    ): FleetPortDisbursement {
        $total = $portDues + $pilotage + $towage + $berthHire + $linesmen + $wasteReception + $agencyFee + $customs;

        return FleetPortDisbursement::create([
            'port_call_id' => $portCallId,
            'disbursement_type' => 'PDA',
            'account_reference' => $accountRef,
            'port_dues_usd' => round($portDues, 2),
            'pilotage_usd' => round($pilotage, 2),
            'towage_usd' => round($towage, 2),
            'berth_hire_usd' => round($berthHire, 2),
            'linesmen_usd' => round($linesmen, 2),
            'waste_reception_usd' => round($wasteReception, 2),
            'agency_fee_usd' => round($agencyFee, 2),
            'customs_immigration_usd' => round($customs, 2),
            'total_disbursement_usd' => round($total, 2),
            'variance_with_pda_usd' => 0.0,
            'status' => 'SUBMITTED',
        ]);
    }

    public function finalizeDisbursementAccount(
        int $portCallId,
        string $accountRef,
        float $actualPortDues,
        float $actualPilotage,
        float $actualTowage,
        float $actualBerthHire,
        float $actualLinesmen = 450.0,
        float $actualWasteReception = 600.0,
        float $actualAgencyFee = 2500.0,
        float $actualCustoms = 800.0
    ): FleetPortDisbursement {
        $actualTotal = $actualPortDues + $actualPilotage + $actualTowage + $actualBerthHire + $actualLinesmen + $actualWasteReception + $actualAgencyFee + $actualCustoms;

        $pda = FleetPortDisbursement::where('port_call_id', $portCallId)
            ->where('disbursement_type', 'PDA')
            ->first();

        $pdaTotal = $pda ? $pda->total_disbursement_usd : $actualTotal;
        $variance = $actualTotal - $pdaTotal;

        return FleetPortDisbursement::create([
            'port_call_id' => $portCallId,
            'disbursement_type' => 'FDA',
            'account_reference' => $accountRef,
            'port_dues_usd' => round($actualPortDues, 2),
            'pilotage_usd' => round($actualPilotage, 2),
            'towage_usd' => round($actualTowage, 2),
            'berth_hire_usd' => round($actualBerthHire, 2),
            'linesmen_usd' => round($actualLinesmen, 2),
            'waste_reception_usd' => round($actualWasteReception, 2),
            'agency_fee_usd' => round($actualAgencyFee, 2),
            'customs_immigration_usd' => round($actualCustoms, 2),
            'total_disbursement_usd' => round($actualTotal, 2),
            'variance_with_pda_usd' => round($variance, 2),
            'status' => 'AUDITED',
        ]);
    }

    public function recordWasteDisposal(
        int $portCallId,
        string $marpolAnnex,
        float $quantityM3,
        string $facilityName,
        string $certNumber
    ): FleetWasteDisposal {
        return FleetWasteDisposal::create([
            'port_call_id' => $portCallId,
            'marpol_annex' => $marpolAnnex,
            'quantity_m3' => $quantityM3,
            'reception_facility_name' => $facilityName,
            'receipt_certificate_number' => $certNumber,
            'disposed_at' => CarbonImmutable::now()->toDateTimeString(),
        ]);
    }
}
