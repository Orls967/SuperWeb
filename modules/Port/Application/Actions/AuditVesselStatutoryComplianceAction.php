<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Domain\Models\FleetCertificate;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Action Audit Sertifikasi Statutoria Kapal (SOLAS / MARPOL / STCW).
 */
final class AuditVesselStatutoryComplianceAction
{
    public function __construct(
        private readonly PortFleetService $fleetService
    ) {}

    public function execute(int $vesselId, CarbonImmutable $targetDate): array
    {
        $vessel = FleetVessel::with(['certificates', 'crewComplement', 'bunkerTanks'])->findOrFail($vesselId);

        $statutoryValid = $vessel->hasValidStatutoryCertificates($targetDate);
        $crewCompliant = $vessel->crewComplement->first()?->isAdequatelyManned() ?? false;
        $totalRob = $vessel->totalRobMetricTons();

        $expiringSoon = [];
        $windowDays = 60; // Peringatan 60 hari sebelum kedaluwarsa

        foreach ($vessel->certificates as $cert) {
            $expiry = CarbonImmutable::parse($cert->expiry_date);
            if ($expiry->isBefore($targetDate)) {
                $cert->update(['status' => 'EXPIRED']);
            } elseif ($expiry->diffInDays($targetDate) <= $windowDays) {
                $cert->update(['status' => 'EXPIRING_SOON']);
                $expiringSoon[] = [
                    'cert_type' => $cert->certificate_type,
                    'cert_number' => $cert->certificate_number,
                    'expiry_date' => $cert->expiry_date->toDateString(),
                    'days_remaining' => $expiry->diffInDays($targetDate),
                ];
            }
        }

        return [
            'vessel_id' => $vessel->id,
            'imo_number' => $vessel->imo_number,
            'vessel_name' => $vessel->vessel_name,
            'is_seaworthy' => $statutoryValid && $crewCompliant && ($totalRob > 0.0),
            'statutory_valid' => $statutoryValid,
            'crew_compliant' => $crewCompliant,
            'total_rob_mt' => $totalRob,
            'expiring_soon_count' => count($expiringSoon),
            'expiring_soon_certificates' => $expiringSoon,
        ];
    }
}
