<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Operations;

use DomainException;
use Modules\Hospital\Application\DTOs\WasteManifestRecordDTO;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;

final class HandoverMedicalWasteAction
{
    public function __construct(
        private readonly MedicalWasteManifestService $wasteService
    ) {}

    /**
     * @param  array{
     *     manifest_id: int,
     *     transporter_company_name: string,
     *     vehicle_plate_no: string,
     *     driver_name: string,
     *     handover_weight_kg: float
     * }  $data
     */
    public function execute(array $data): WasteManifestRecordDTO
    {
        $manifestId = (int) $data['manifest_id'];
        $manifest = MedicalWasteManifest::find($manifestId);

        if (! $manifest) {
            throw new DomainException("Manifest limbah B3 #{$manifestId} tidak ditemukan.");
        }

        return $this->wasteService->handoverToTransporter(
            manifest: $manifest,
            transporterCompanyName: $data['transporter_company_name'],
            vehiclePlateNo: $data['vehicle_plate_no'],
            driverName: $data['driver_name'],
            handoverWeightKg: (float) $data['handover_weight_kg']
        );
    }
}
