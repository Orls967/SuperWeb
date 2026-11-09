<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

/**
 * Aksi rotasi salt vault blind-index PII dengan pelacakan versi salt.
 */
class RotatePiiSaltAction
{
    /**
     * @return array{previous_version: int, new_version: int, new_salt_fingerprint: string}
     */
    public function rotate(int $currentVersion = 1): array
    {
        $newVersion = $currentVersion + 1;
        $newSalt = bin2hex(random_bytes(16));
        $fingerprint = substr(hash('sha256', $newSalt), 0, 12);

        return [
            'previous_version' => $currentVersion,
            'new_version' => $newVersion,
            'new_salt_fingerprint' => $fingerprint,
        ];
    }
}
