<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Verifikator sertifikat kriptografis untuk algoritma E14 Black-Start Sequencer.
 */
final class BlackStartSynchronizationCertificateVerifier
{
    public function verify(BlackStartSynchronizationResult $result): bool
    {
        $codes = array_map(fn ($s) => $s['component_code'], $result->sequence);
        $seqCodes = implode('->', $codes);

        $expectedPayload = sprintf(
            'BLACK-START|GRID:%s|COUNT:%d|TOTAL_SEC:%d|SEQ:[%s]',
            $result->microgridCode,
            $result->totalComponentsCount,
            $result->totalEstimatedSeconds,
            $seqCodes
        );

        if ($expectedPayload !== $result->canonicalPayload) {
            throw new RuntimeException('Payload kanonikal hasil black-start tidak cocok.');
        }

        $expectedHash = hash('sha256', $expectedPayload);
        if ($expectedHash !== $result->certificateHash) {
            throw new RuntimeException('Hash sertifikat black-start sequencer tidak valid.');
        }

        return true;
    }
}
