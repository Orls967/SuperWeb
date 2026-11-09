<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\SpecimenCustodyHashChainTracker;
use Modules\Hospital\Domain\DTO\SpecimenCustodyTransferDTO;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\SpecimenCustodyChainLog;
use RuntimeException;

class TransferSpecimenCustodyAction
{
    public function __construct(
        protected SpecimenCustodyHashChainTracker $tracker
    ) {}

    public function execute(SpecimenCustodyTransferDTO $dto): SpecimenCustodyChainLog
    {
        /** @var LabSpecimen|null $specimen */
        $specimen = LabSpecimen::find($dto->specimenId);

        if (! $specimen) {
            throw new RuntimeException("Spesimen dengan ID [{$dto->specimenId}] tidak ditemukan.");
        }

        return $this->tracker->recordHop(
            specimen: $specimen,
            hopNumber: $dto->hopNumber,
            fromHandler: $dto->fromHandler,
            toHandler: $dto->toHandler,
            temperatureCelsius: $dto->temperatureCelsius,
            notes: $dto->notes,
            gpsCoordinates: $dto->gpsCoordinates,
            containerBarcode: $dto->containerBarcode
        );
    }
}
