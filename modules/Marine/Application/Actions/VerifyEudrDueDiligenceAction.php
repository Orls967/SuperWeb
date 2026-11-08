<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\EudrDeforestationVerificationService;
use Modules\Marine\Domain\Models\EudrDeforestationCertification;

class VerifyEudrDueDiligenceAction
{
    protected EudrDeforestationVerificationService $eudrService;

    public function __construct(EudrDeforestationVerificationService $eudrService)
    {
        $this->eudrService = $eudrService;
    }

    /**
     * @param  array<string, mixed>  $geoData
     */
    public function execute(int $batchId, array $geoData): EudrDeforestationCertification
    {
        return $this->eudrService->verifyBatchDueDiligence($batchId, $geoData);
    }
}
