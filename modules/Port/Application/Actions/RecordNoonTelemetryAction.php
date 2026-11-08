<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\VoyageExecutionService;
use Modules\Port\Domain\Models\FleetNoonReport;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Action Validasi & Pencatatan Telemetri Noon Report Harian.
 */
final class RecordNoonTelemetryAction
{
    public function __construct(
        private readonly VoyageExecutionService $voyageService
    ) {}

    public function execute(
        int $voyageId,
        CarbonImmutable $reportDate,
        float $latitude,
        float $longitude,
        float $courseDegrees,
        float $speedGpsKnots,
        float $distanceLast24hNm,
        float $windBeaufort,
        string $windDirection,
        float $seaStateDouglas,
        float $engineRpm,
        float $engineSlipPercentage,
        float $vlsfoConsumed24hMt,
        float $lsmgoConsumed24hMt,
        ?string $remarks = null
    ): FleetNoonReport {
        $voyage = FleetVoyage::findOrFail($voyageId);

        // Validasi koordinat geografis maritim
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new DomainException('Latitude harus berada di antara -90.0 dan 90.0 derajat.');
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new DomainException('Longitude harus berada di antara -180.0 dan 180.0 derajat.');
        }

        if ($courseDegrees < 0.0 || $courseDegrees >= 360.0) {
            throw new DomainException('Haluan kapal (course) harus berada di rentang 0.0 s.d. 359.9 derajat.');
        }

        if ($speedGpsKnots < 0.0 || $speedGpsKnots > 35.0) {
            throw new DomainException('Kecepatan kapal tidak wajar.');
        }

        if ($windBeaufort < 0.0 || $windBeaufort > 12.0) {
            throw new DomainException('Skala angin Beaufort harus berada di rentang 0 sampai 12.');
        }

        // Peringatan otomatis jika slip propeller tinggi (kondisi badai atau biofouling lambung)
        $enrichedRemarks = $remarks ?? '';
        if ($engineSlipPercentage > 8.0) {
            $enrichedRemarks .= " [PERINGATAN TELEMETRI: Slip propeller tinggi ({$engineSlipPercentage}%), terdeteksi hambatan hidrodinamis]";
        }

        return $this->voyageService->recordNoonReport(
            $voyage->id,
            $reportDate,
            $latitude,
            $longitude,
            $courseDegrees,
            $speedGpsKnots,
            $distanceLast24hNm,
            $windBeaufort,
            $windDirection,
            $seaStateDouglas,
            $engineRpm,
            $engineSlipPercentage,
            $vlsfoConsumed24hMt,
            $lsmgoConsumed24hMt,
            trim($enrichedRemarks)
        );
    }
}
