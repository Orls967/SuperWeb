<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use DomainException;
use Modules\Hospital\Domain\Services\HospitalExternalFactors;

/**
 * Aksi Pengaktifan Status Siaga Darurat Banjir Akses RS (T-5 / Fase 87.6).
 */
class ActivateEmergencyFloodStandbyAction
{
    public function __construct(
        protected readonly HospitalExternalFactors $externalFactors
    ) {}

    public function execute(string $cityCode = 'JKT', ?int $actorId = null): array
    {
        $blockedHours = $this->externalFactors->getFactor('flood_access_blocked_hours', $cityCode);
        if ($blockedHours < 2) {
            throw new DomainException('Akses jalan RS belum terputus banjir melebihi ambang batas 2 jam.');
        }

        return [
            'flood_standby_activated' => true,
            'blocked_hours' => $blockedHours,
            'helipad_readiness' => 'STANDBY',
            'ambulance_reroute' => 'ACTIVE',
            'message' => "Status siaga darurat banjir aktif untuk wilayah {$cityCode} (terputus {$blockedHours} jam).",
        ];
    }
}
