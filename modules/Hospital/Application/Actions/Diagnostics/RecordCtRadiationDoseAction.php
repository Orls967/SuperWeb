<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\CtRadiationDoseTracker;
use Modules\Hospital\Domain\DTO\RadiationDosimetryDTO;
use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\RadiationDosimetryLog;
use RuntimeException;

class RecordCtRadiationDoseAction
{
    public function __construct(
        protected CtRadiationDoseTracker $tracker
    ) {}

    /**
     * @return array{is_drl_exceeded: bool, effective_dose_msv: float, ssde_mgy: float, log: RadiationDosimetryLog}
     */
    public function execute(RadiationDosimetryDTO $dto): array
    {
        /** @var ImagingStudy|null $study */
        $study = ImagingStudy::find($dto->studyId);

        if (! $study) {
            throw new RuntimeException("Studi radiologi ID [{$dto->studyId}] tidak ditemukan.");
        }

        return $this->tracker->recordDoseMetrics(
            study: $study,
            ctdiVolMgy: $dto->ctdiVolMgy,
            scanLengthCm: $dto->scanLengthCm,
            patientEffectiveDiameterCm: $dto->patientDiameterCm
        );
    }
}
