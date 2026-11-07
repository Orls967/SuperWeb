<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Underground;

final class HardyCrossVentilationNetworkVerifier
{
    private const SECRET = 'hardy-cross-ventilation-network-secret-key-2026';

    public static function sign(
        array $finalFlowsQ,
        int $iterationsCount,
        bool $converged,
        float $totalFanPowerKw
    ): string {
        $payload = sprintf(
            'flows:%s|iter:%d|conv:%d|pwr:%.4f',
            json_encode($finalFlowsQ),
            $iterationsCount,
            $converged ? 1 : 0,
            $totalFanPowerKw
        );

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(
        HardyCrossVentilationNetworkSolverResult $result
    ): bool {
        $expected = self::sign(
            $result->finalFlowsQ,
            $result->iterationsCount,
            $result->converged,
            $result->totalFanPowerKw
        );

        return hash_equals($expected, $result->hmacSignature);
    }
}
