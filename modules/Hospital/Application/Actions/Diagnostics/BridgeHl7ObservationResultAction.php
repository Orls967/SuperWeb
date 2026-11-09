<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\OutsourcedLabBridgingGateway;
use Modules\Hospital\Domain\Models\LabResult;
use Modules\Hospital\Domain\Models\LabSpecimen;
use RuntimeException;

class BridgeHl7ObservationResultAction
{
    public function __construct(
        protected OutsourcedLabBridgingGateway $gateway
    ) {}

    /**
     * @param  array{message_control_id: string, obr_order_num: string, obx_segments: list<array{loinc_code: string, value: float|string, units: string, abnormal_flag: string}>}  $hl7Payload
     * @return list<LabResult>
     */
    public function execute(int $specimenId, array $hl7Payload): array
    {
        /** @var LabSpecimen|null $specimen */
        $specimen = LabSpecimen::find($specimenId);

        if (! $specimen) {
            throw new RuntimeException("Spesimen ID [{$specimenId}] tidak ditemukan.");
        }

        return $this->gateway->ingestHl7ObservationResults($specimen, $hl7Payload);
    }
}
