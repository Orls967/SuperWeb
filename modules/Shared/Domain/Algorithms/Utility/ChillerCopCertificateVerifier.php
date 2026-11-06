<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Memverifikasi keaslian dan integritas sertifikat hasil komputasi efisiensi Chiller COP.
 */
final class ChillerCopCertificateVerifier
{
    /**
     * Verifikasi kesesuaian hash sertifikat dengan payload kanonikal.
     *
     * @throws RuntimeException Jika verifikasi integritas gagal
     */
    public function verify(ChillerCopResult $result): bool
    {
        $expectedHash = hash('sha256', $result->canonicalPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new RuntimeException(
                "Integritas sertifikat Chiller COP terkorupsi: Hash {$result->certificateHash} != {$expectedHash}"
            );
        }

        // Verifikasi rekonstruksi independen dari field kanonikal
        $reconstructedSignature = sprintf(
            'CHILLER-COP|RT:%d|KW:%d|DES:%d|ACT:%d|COP:%d|DEV:%d|FLW:%d|DT:%d|STAT:%s',
            $result->coolingTons,
            $result->electricalPowerKw,
            $result->actualKwPerTonBps - $result->deviationBps,
            $result->actualKwPerTonBps,
            $result->copBps,
            $result->deviationBps,
            $result->chilledWaterFlowLps,
            $result->deltaTempTenthCelsius,
            $result->operationalStatus
        );

        if (! hash_equals($reconstructedSignature, $result->canonicalPayload)) {
            throw new RuntimeException('Payload kanonikal sertifikat Chiller COP tidak konsisten dengan metrik numerik.');
        }

        return true;
    }
}
