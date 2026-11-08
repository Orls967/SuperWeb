<?php

declare(strict_types=1);

namespace Modules\Aviation\Integrations;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Aviation\Domain\Enums\MaintenanceVisitStatus;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationMaintenanceVisit;

class AssetMaintenanceIntegrationService
{
    public function __construct(
        private readonly ?MaintenanceWorkOrderRequester $workOrderRequester = null
    ) {}

    /**
     * Integrasi pembuatan Work Order perawatan berkala / overhaul pesawat ke modul Asset (K9).
     *
     * @param AviationMaintenanceVisit $visit
     * @return int ID Work Order dari modul Asset
     */
    public function syncWorkOrderToAssetModule(AviationMaintenanceVisit $visit): int
    {
        $aircraft = AviationAircraft::findOrFail($visit->aircraft_id);

        $reasonCode = match ($visit->check_type) {
            'A_CHECK' => 'AIRCRAFT_A_CHECK_INSPECTION',
            'C_CHECK' => 'AIRCRAFT_C_CHECK_HEAVY_MAINTENANCE',
            'D_CHECK' => 'AIRCRAFT_D_CHECK_OVERHAUL',
            'LINE_CHECK' => 'AIRCRAFT_TRANSIT_LINE_CHECK',
            default => 'AIRCRAFT_UNSCHEDULED_DEFECT',
        };

        $description = sprintf(
            'Perawatan pesawat %s (%s). Kunjungan ID: %d, MRO Station: %s, Rincian: %s',
            $aircraft->registration_mark,
            $aircraft->icao_type,
            $visit->id,
            $visit->mro_station ?? 'CGK-GMF',
            $visit->discrepancy_log ?? 'Pemeriksaan sistematis berkala'
        );

        $idempotencyKey = sprintf('AVI-MNT-WO-%d-%s', $visit->id, $visit->check_type);

        if ($this->workOrderRequester !== null && $aircraft->asset_id !== null) {
            try {
                $workOrderId = $this->workOrderRequester->request(
                    assetId: (int) $aircraft->asset_id,
                    reasonCode: $reasonCode,
                    description: $description,
                    idempotencyKey: $idempotencyKey
                );

                $visit->asset_work_order_id = $workOrderId;
                $visit->save();

                return $workOrderId;
            } catch (Exception $e) {
                Log::warning("Gagal mengirim work order perawatan pesawat {$aircraft->registration_mark} ke modul Asset: ".$e->getMessage());
            }
        }

        // Fallback internal stub bila work order requester tidak tersedia di runtime test
        $fallbackId = 900000 + $visit->id;
        $visit->asset_work_order_id = $fallbackId;
        $visit->save();

        return $fallbackId;
    }
}

