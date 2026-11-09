<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Operations;

use Modules\Hospital\Application\DTOs\WasteManifestRecordDTO;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;

final class LogMedicalWasteAction
{
    public function __construct(
        private readonly MedicalWasteManifestService $wasteService
    ) {}

    /**
     * @param  array{waste_category: string, weight_kg: float, source_ward_code: string, handler_staff_name: string}  $data
     */
    public function execute(array $data): WasteManifestRecordDTO
    {
        return $this->wasteService->registerWasteFromWard(
            wasteCategory: $data['waste_category'],
            weightKg: (float) $data['weight_kg'],
            sourceWardCode: $data['source_ward_code'],
            handlerStaffName: $data['handler_staff_name']
        );
    }
}
