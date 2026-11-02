<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Services;

use Modules\Shared\Domain\Math\DagExplosionCertificate;
use Modules\Shared\Domain\Math\DagExplosionCertificateVerifier;

final readonly class BOMVerificationCertificate
{
    public function __construct(
        public int $recipeId,
        public int $totalCostIdr,
        public DagExplosionCertificate $dagCertificate,
        public array $rawMaterials,
    ) {}

    public function isValid(): bool
    {
        try {
            DagExplosionCertificateVerifier::verify($this->dagCertificate);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
