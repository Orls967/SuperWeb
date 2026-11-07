<?php

declare(strict_types=1);

namespace Modules\Hospital\Contracts;

/**
 * G7-K23: Kontrak Aditif Penugasan Respons Cepat Medis & Ambulans Venue.
 */
interface MedicalEmergencyResponseDispatcher
{
    /**
     * Kirim tim medis darurat ke lokasi venue dan zona terdampak.
     *
     * @return array{
     *   dispatch_code: string,
     *   venue_id: int,
     *   zone_id: int,
     *   triage_level: string,
     *   dispatched_ambulance_count: int,
     *   paramedic_team_size: int,
     *   estimated_eta_minutes: int,
     *   status: string
     * }
     */
    public function dispatchMedicalTeam(int $venueId, int $zoneId, string $triageLevel, string $reason): array;
}
