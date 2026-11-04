<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Hash;

use InvalidArgumentException;

/**
 * Verifier sertifikat bukti keanggotaan (Merkle proof) Merkle Tree.
 */
final class MerkleCertificateVerifier
{
    /**
     * Memverifikasi apakah daun dengan proof yang diberikan menghasilkan root yang cocok.
     *
     * @param string $leaf Data daun asli (string)
     * @param list<array{position: string, hash: string}> $proof Bukti lintasan Merkle
     * @param string $expectedRoot Hex root hash yang diharapkan (64 chars)
     */
    public function verify(string $leaf, array $proof, string $expectedRoot): bool
    {
        if ($expectedRoot === '') {
            return false;
        }

        $currentHash = hash('sha256', $leaf, true);

        foreach ($proof as $step) {
            if (! isset($step['position'], $step['hash'])) {
                throw new InvalidArgumentException('Format proof tidak valid: setiap langkah wajib memiliki position dan hash');
            }

            if (! ctype_xdigit($step['hash']) || strlen($step['hash']) !== 64) {
                throw new InvalidArgumentException('Hash proof tidak valid: harus berupa hex string 64 karakter');
            }

            $siblingHash = hex2bin($step['hash']);

            if ($step['position'] === 'left') {
                $currentHash = hash('sha256', $siblingHash . $currentHash, true);
            } elseif ($step['position'] === 'right') {
                $currentHash = hash('sha256', $currentHash . $siblingHash, true);
            } else {
                throw new InvalidArgumentException('Posisi proof tidak valid: harus "left" atau "right"');
            }
        }

        return bin2hex($currentHash) === strtolower($expectedRoot);
    }
}
