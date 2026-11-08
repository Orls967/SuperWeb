<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Logistics\Contracts\VesselRegistry;
use Modules\Port\Domain\Models\FleetBunkerTank;
use Modules\Port\Domain\Models\FleetCertificate;
use Modules\Port\Domain\Models\FleetClass;
use Modules\Port\Domain\Models\FleetCrewComplement;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Service Manajemen Armada Kapal Niaga & Registrasi Maritim.
 * Mengimplementasikan kontrak VesselRegistry (Logistics K4) untuk akses lintas-modul.
 */
final class PortFleetService implements VesselRegistry
{
    /**
     * Daftarkan kapal niaga baru ke dalam armada.
     *
     * @param array{
     *     imo_number: string,
     *     vessel_name: string,
     *     call_sign?: string,
     *     mmsi?: string,
     *     flag_state?: string,
     *     home_port?: string,
     *     vessel_type: string,
     *     fleet_class_code?: string,
     *     built_year?: int,
     *     deadweight_tonnage: int,
     *     gross_tonnage: int,
     *     net_tonnage: int,
     *     length_overall_cm: int,
     *     beam_cm: int,
     *     design_draft_cm: int,
     *     scantling_draft_cm?: int,
     *     main_engine_power_kw: int,
     *     design_speed_knots?: float,
     *     sfoc_design_grams_kwh?: float,
     *     fuel_types_supported?: string,
     *     cii_reference_a?: float,
     *     cii_reference_c?: float,
     *     metadata?: array
     * } $data
     */
    public function registerVessel(array $data): FleetVessel
    {
        $classId = null;
        if (! empty($data['fleet_class_code'])) {
            $class = FleetClass::firstOrCreate(
                ['code' => $data['fleet_class_code']],
                [
                    'society_name' => $data['fleet_class_code'],
                    'class_notation' => '100A1 Bulk Carrier ESP, LI',
                    'status' => 'ACTIVE',
                ]
            );
            $classId = $class->id;
        }

        return FleetVessel::create([
            'imo_number' => $data['imo_number'],
            'vessel_name' => $data['vessel_name'],
            'call_sign' => $data['call_sign'] ?? null,
            'mmsi' => $data['mmsi'] ?? null,
            'flag_state' => $data['flag_state'] ?? 'ID',
            'home_port' => $data['home_port'] ?? 'Tanjung Priok',
            'vessel_type' => $data['vessel_type'],
            'fleet_class_id' => $classId,
            'built_year' => $data['built_year'] ?? 2020,
            'deadweight_tonnage' => $data['deadweight_tonnage'],
            'gross_tonnage' => $data['gross_tonnage'],
            'net_tonnage' => $data['net_tonnage'],
            'length_overall_cm' => $data['length_overall_cm'],
            'beam_cm' => $data['beam_cm'],
            'design_draft_cm' => $data['design_draft_cm'],
            'scantling_draft_cm' => $data['scantling_draft_cm'] ?? (int) ($data['design_draft_cm'] * 1.1),
            'main_engine_power_kw' => $data['main_engine_power_kw'],
            'design_speed_knots' => $data['design_speed_knots'] ?? 14.50,
            'sfoc_design_grams_kwh' => $data['sfoc_design_grams_kwh'] ?? 175.50,
            'fuel_types_supported' => $data['fuel_types_supported'] ?? 'VLSFO,LSMGO',
            'cii_reference_a' => $data['cii_reference_a'] ?? 4745.0,
            'cii_reference_c' => $data['cii_reference_c'] ?? 0.4220,
            'operational_status' => 'IN_SERVICE',
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Konfigurasi tangki bunker kapal.
     */
    public function configureBunkerTank(
        int $vesselId,
        string $tankCode,
        string $tankName,
        string $fuelGrade,
        float $capacityM3,
        float $initialRobMetricTons = 0.0
    ): FleetBunkerTank {
        $safeCapacity = $capacityM3 * 0.98; // Aturan 98% SOLAS/MARPOL untuk ekspansi termal bahan bakar
        $density = $fuelGrade === 'LSMGO' ? 0.86 : 0.991;
        $initialM3 = $density > 0 ? ($initialRobMetricTons / $density) : 0.0;

        return FleetBunkerTank::create([
            'vessel_id' => $vesselId,
            'tank_code' => $tankCode,
            'tank_name' => $tankName,
            'fuel_grade' => $fuelGrade,
            'capacity_m3' => $capacityM3,
            'safe_capacity_m3' => $safeCapacity,
            'current_rob_m3' => round($initialM3, 2),
            'current_rob_metric_tons' => round($initialRobMetricTons, 2),
            'sounding_depth_cm' => 0.0,
        ]);
    }

    /**
     * Terbitkan atau perbarui sertifikat statutoria kapal.
     */
    public function recordCertificate(
        int $vesselId,
        string $certType,
        string $certNumber,
        string $authority,
        CarbonImmutable $issueDate,
        CarbonImmutable $expiryDate,
        ?CarbonImmutable $endorsementDue = null
    ): FleetCertificate {
        return FleetCertificate::updateOrCreate(
            [
                'vessel_id' => $vesselId,
                'certificate_type' => $certType,
            ],
            [
                'certificate_number' => $certNumber,
                'issuing_authority' => $authority,
                'issue_date' => $issueDate->toDateString(),
                'expiry_date' => $expiryDate->toDateString(),
                'annual_endorsement_due' => $endorsementDue?->toDateString(),
                'status' => 'VALID',
            ]
        );
    }

    /**
     * Daftarkan Safe Manning Requirement dan audit kepatuhan STCW.
     */
    public function recordSafeManning(
        int $vesselId,
        string $certNo,
        int $deckOfficers,
        int $engineOfficers,
        int $deckRatings,
        int $engineRatings,
        int $catering,
        int $actualOnboard
    ): FleetCrewComplement {
        $totalRequired = $deckOfficers + $engineOfficers + $deckRatings + $engineRatings + $catering;
        $isCompliant = ($actualOnboard >= $totalRequired);

        return FleetCrewComplement::updateOrCreate(
            ['vessel_id' => $vesselId],
            [
                'minimum_safe_manning_cert_no' => $certNo,
                'deck_officers_required' => $deckOfficers,
                'engine_officers_required' => $engineOfficers,
                'deck_ratings_required' => $deckRatings,
                'engine_ratings_required' => $engineRatings,
                'catering_crew_required' => $catering,
                'total_crew_onboard' => $actualOnboard,
                'stcw_compliant' => $isCompliant,
                'last_inspected_at' => CarbonImmutable::now()->toDateString(),
            ]
        );
    }

    /**
     * Audit kesiapan operasional kapal untuk voyage niaga.
     *
     * @return array{
     *     vessel_id: int,
     *     imo: string,
     *     name: string,
     *     is_seaworthy: bool,
     *     statutory_valid: bool,
     *     crew_compliant: bool,
     *     total_rob_mt: float,
     *     reasons: array<int, string>
     * }
     */
    public function auditSeaworthiness(int $vesselId, CarbonImmutable $voyageDate): array
    {
        $vessel = FleetVessel::with(['certificates', 'crewComplement', 'bunkerTanks'])->findOrFail($vesselId);
        $reasons = [];

        $statutoryValid = $vessel->hasValidStatutoryCertificates($voyageDate);
        if (! $statutoryValid) {
            $reasons[] = 'Satu atau lebih sertifikat statutoria telah kedaluwarsa atau belum terbit.';
        }

        $crewComplement = $vessel->crewComplement->first();
        $crewCompliant = $crewComplement ? $crewComplement->isAdequatelyManned() : false;
        if (! $crewCompliant) {
            $reasons[] = 'Komposisi awak kapal tidak memenuhi Safe Manning Document (STCW 1978/2010).';
        }

        $totalRob = $vessel->totalRobMetricTons();
        if ($totalRob <= 0.0) {
            $reasons[] = 'Sisa bahan bakar di atas kapal (ROB) kosong.';
        }

        $isSeaworthy = $statutoryValid && $crewCompliant && ($totalRob > 0.0);

        return [
            'vessel_id' => $vessel->id,
            'imo' => $vessel->imo_number,
            'name' => $vessel->vessel_name,
            'is_seaworthy' => $isSeaworthy,
            'statutory_valid' => $statutoryValid,
            'crew_compliant' => $crewCompliant,
            'total_rob_mt' => $totalRob,
            'reasons' => $reasons,
        ];
    }

    // =========================================================================
    // Implementasi Kontrak VesselRegistry (Logistics K4)
    // =========================================================================

    public function findByImo(string $imo): ?array
    {
        $vessel = FleetVessel::where('imo_number', $imo)->first();
        if (! $vessel) {
            return null;
        }

        return [
            'id' => (int) $vessel->id,
            'imo' => $vessel->imo_number,
            'name' => $vessel->vessel_name,
            'loa_cm' => (int) $vessel->length_overall_cm,
        ];
    }

    public function containerById(int $containerId): ?array
    {
        // Menyediakan stub kompatibilitas master container
        return [
            'id' => $containerId,
            'number' => 'MSKU'.str_pad((string) $containerId, 7, '0', STR_PAD_LEFT),
            'iso_type' => '40HC',
            'line_party_id' => 1,
        ];
    }
}
