<?php

declare(strict_types=1);

namespace Modules\Esg\Contracts;

use Carbon\CarbonImmutable;

/**
 * Kontrak pencatatan manfaat emisi terhindar dari pemanfaatan material daur ulang (K3).
 */
interface EmissionBenefitRecorder
{
    /**
     * Mencatat emisi terhindar dan mengembalikan gram CO2e yang dihindari (berdasarkan faktor berversi S1).
     *
     * @return int Gram CO2e terhindar
     */
    public function recordAvoidedEmission(
        string $sourceType,
        int $sourceId,
        string $materialCode,
        int $quantityGrams,
        CarbonImmutable $at,
        string $idempotencyKey
    ): int;
}

