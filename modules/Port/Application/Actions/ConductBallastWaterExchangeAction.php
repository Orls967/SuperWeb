<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\VesselBallastWaterManagementService;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Action Verifikasi & Pencatatan Pertukaran Air Ballas Menurut Aturan IMO D-1.
 */
final class ConductBallastWaterExchangeAction
{
    public function __construct(
        private readonly VesselBallastWaterManagementService $ballastService
    ) {}

    public function execute(
        int $voyageId,
        float $distanceFromCoastNm,
        float $waterDepthM,
        float $tankCapacityM3,
        float $volumePumpedM3,
        string $method = 'SEQUENTIAL'
    ): array {
        $voyage = FleetVoyage::findOrFail($voyageId);

        if ($volumePumpedM3 <= 0.0) {
            throw new DomainException('Volume air ballas yang dipompa harus lebih besar dari 0.');
        }

        $audit = $this->ballastService->auditBallastWaterExchangeD1(
            $distanceFromCoastNm,
            $waterDepthM,
            $tankCapacityM3,
            $volumePumpedM3,
            $method
        );

        if (! $audit['is_d1_compliant']) {
            throw new DomainException('Pertukaran air ballas GAGAL memenuhi Regulation B-4 BWMC (jarak < 50 NM, kedalaman < 200m, atau volume < 95%).');
        }

        return [
            'voyage_id' => $voyage->id,
            'audit' => $audit,
            'logged_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
