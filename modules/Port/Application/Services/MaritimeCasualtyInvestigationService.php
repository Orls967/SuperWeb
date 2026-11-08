<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Service Investigasi Kecelakaan Maritim & Analisis VDR (Voyage Data Recorder).
 * Standar: IMO Code of the International Standards and Recommended Practices for a Safety Investigation into a Marine Casualty (Casualty Investigation Code).
 */
final class MaritimeCasualtyInvestigationService
{
    /**
     * Catat dan klasifikasikan insiden maritim menurut IMO MSC-MEPC.3/Circ.3.
     *
     * @param int $vesselId
     * @param string $incidentType COLLISION, GROUNDING, CONTACT, FIRE_EXPLOSION, STRUCTURAL_FAILURE
     * @param string $severity VERY_SERIOUS, SERIOUS, LESS_SERIOUS, MARINE_INCIDENT
     * @param string $locationDescription
     * @param CarbonImmutable $occurredAt
     * @param float $propertyDamageEstimateUsd
     * @param bool $lossOfLife
     * @param bool $severePollution
     * @return array{
     *     vessel_id: int,
     *     incident_reference: string,
     *     classification: string,
     *     mandatory_flag_state_inquiry: bool,
     *     imo_gisis_reportable: bool,
     *     damage_usd: float,
     *     status: string
     * }
     */
    public function logMarineCasualty(
        int $vesselId,
        string $incidentType,
        string $severity,
        string $locationDescription,
        CarbonImmutable $occurredAt,
        float $propertyDamageEstimateUsd = 0.0,
        bool $lossOfLife = false,
        bool $severePollution = false
    ): array {
        $vessel = FleetVessel::findOrFail($vesselId);

        // Kriteria Very Serious Marine Casualty (VSMC): kehilangan kapal total, korban jiwa, atau polusi parah
        $isVerySerious = ($severity === 'VERY_SERIOUS') || $lossOfLife || $severePollution || ($propertyDamageEstimateUsd > 2000000.0);
        $finalSeverity = $isVerySerious ? 'VERY_SERIOUS' : $severity;

        $ref = 'CAS-' . strtoupper(substr($incidentType, 0, 4)) . '-' . bin2hex(random_bytes(3));

        return [
            'vessel_id' => $vessel->id,
            'incident_reference' => $ref,
            'classification' => $finalSeverity,
            'mandatory_flag_state_inquiry' => $isVerySerious,
            'imo_gisis_reportable' => in_array($finalSeverity, ['VERY_SERIOUS', 'SERIOUS'], true),
            'damage_usd' => round($propertyDamageEstimateUsd, 2),
            'status' => 'UNDER_SAFETY_INVESTIGATION',
        ];
    }

    /**
     * Audit dan ekstrak telemetri Voyage Data Recorder (VDR) untuk investigasi rekonstruksi insiden.
     */
    public function auditVdrPlaybackTelemetry(
        array $vdrChannels,
        CarbonImmutable $incidentWindowStart,
        CarbonImmutable $incidentWindowEnd
    ): array {
        $requiredChannels = ['HEADING_GYRO', 'SPEED_LOG', 'RUDDER_ORDER', 'ENGINE_RPM', 'VHF_AUDIO'];
        $missingChannels = [];

        foreach ($requiredChannels as $req) {
            if (! in_array($req, $vdrChannels, true)) {
                $missingChannels[] = $req;
            }
        }

        $isVdrIntegral = empty($missingChannels);

        return [
            'is_vdr_integral' => $isVdrIntegral,
            'channels_extracted_count' => count($vdrChannels),
            'missing_required_channels' => $missingChannels,
            'investigation_admissibility' => $isVdrIntegral ? 'ADMISSIBLE_LEGAL_EVIDENCE' : 'INCOMPLETE_TELEMETRY',
        ];
    }
}
