<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\OutsourcedLabBridgingGateway;
use Modules\Hospital\Domain\Models\LabOutsourcingManifest;
use Modules\Hospital\Domain\Models\LabPartnerContract;
use Modules\Hospital\Domain\Models\LabSpecimen;
use RuntimeException;

class DispatchOutsourcedLabManifestAction
{
    public function __construct(
        protected OutsourcedLabBridgingGateway $gateway
    ) {}

    /**
     * @param  list<int>  $specimenIds
     */
    public function execute(
        int $contractId,
        array $specimenIds,
        string $courierBatchBarcode,
        float $departureTempCelsius = 4.0
    ): LabOutsourcingManifest {
        /** @var LabPartnerContract|null $contract */
        $contract = LabPartnerContract::find($contractId);

        if (! $contract) {
            throw new RuntimeException("Kontrak rekanan lab ID [{$contractId}] tidak ditemukan.");
        }

        $specimens = LabSpecimen::whereIn('id', $specimenIds)->get()->all();

        return $this->gateway->dispatchManifest(
            contract: $contract,
            specimens: $specimens,
            courierBatchBarcode: $courierBatchBarcode,
            departureTempCelsius: $departureTempCelsius
        );
    }
}
