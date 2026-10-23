<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts;

use Modules\Core\Application\DTOs\Resilience\DataSovereigntyCheckDto;
use Modules\Core\Application\DTOs\Resilience\KmsRotationDto;
use Modules\Core\Domain\Models\CoreKmsKeyRotation;

interface DataSovereigntyComplianceInterface
{
    /**
     * Evaluate data residency against national data sovereignty laws (e.g. UU PDP).
     *
     * @return array{allowed: bool, violation: ?string, regulation: string}
     */
    public function evaluateResidency(DataSovereigntyCheckDto $dto): array;

    /**
     * Rotate KMS master key deterministically and register fingerprint audit record.
     */
    public function rotateKmsKey(KmsRotationDto $dto): CoreKmsKeyRotation;
}
