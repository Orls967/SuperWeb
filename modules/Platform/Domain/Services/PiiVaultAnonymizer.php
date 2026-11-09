<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

/**
 * Vault anonimisasi dan pencarian blind-index berbasis HMAC (W8-D10).
 */
class PiiVaultAnonymizer
{
    private string $salt;

    public function __construct(?string $salt = null)
    {
        $this->salt = $salt ?? 'PlatformPiiVaultSalt_W8_2026';
    }

    /**
     * Menghasilkan blind index HMAC yang aman untuk pencarian eksak tanpa membocorkan plaintext
     */
    public function generateBlindIndex(string $plaintext): string
    {
        $normalized = strtolower(trim($plaintext));

        return hash_hmac('sha256', $normalized, $this->salt);
    }

    /**
     * Tokenisasi pseudonim yang tidak dapat dibalik tanpa kunci rahasia
     */
    public function pseudonymize(string $identifier, string $prefix = 'TOK'): string
    {
        $hash = hash_hmac('sha256', $identifier, $this->salt);

        return sprintf('%s-%s-%s', $prefix, substr($hash, 0, 8), substr($hash, 8, 8));
    }
}
