<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

/**
 * Vault penukaran token PII reversibel aman menggunakan enkripsi AES-256-GCM.
 */
class PiiDeterministicTokenVault
{
    private string $encryptionKey;

    public function __construct(?string $key = null)
    {
        $this->encryptionKey = $key ?? hash('sha256', 'PlatformPiiEncryptionKey_W8_2026', true);
    }

    /**
     * Mengenkripsi data sensitif menjadi token berstempel waktu
     */
    public function tokenize(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * Mengembalikan plaintext dari token terenkripsi
     */
    public function detokenize(string $token): ?string
    {
        $data = base64_decode($token, true);
        if ($data === false || strlen($data) < 28) {
            return null;
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted !== false ? $decrypted : null;
    }
}
