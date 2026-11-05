<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use InvalidArgumentException;

/**
 * Layanan Enkripsi & Tokenisasi Data Privasi Telemetri Kesehatan (KONSEP §A7 & Fase 159.3).
 * Menggunakan AES-256-GCM berkunci sistem dengan audit tag otentikasi untuk melindungi
 * data sensitif rekam medis wearable pengguna.
 */
final class WellnessVaultPrivacyService
{
    private string $key;

    public function __construct(?string $key = null)
    {
        $this->key = $key ?? (string) config('app.key', 'base64:'.base64_encode(str_repeat('K', 32)));
        if (str_starts_with($this->key, 'base64:')) {
            $this->key = (string) base64_decode(substr($this->key, 7));
        }
    }

    /**
     * Enkripsi payload telemetri medis sebelum disimpan ke log persistensi.
     *
     * @param  array<string, mixed>  $sensitiveData
     */
    public function encryptPayload(array $sensitiveData): string
    {
        $plaintext = json_encode($sensitiveData, JSON_THROW_ON_ERROR);
        $iv = random_bytes(12); // GCM standard 96-bit IV
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new InvalidArgumentException('Gagal mengenkripsi data telemetri medis.');
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * Dekripsi payload telemetri sensitif dengan verifikasi tag otentikasi GCM.
     *
     * @return array<string, mixed>
     */
    public function decryptPayload(string $encryptedToken): array
    {
        $raw = base64_decode($encryptedToken, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new InvalidArgumentException('Format token data vault tidak valid.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new InvalidArgumentException('Dekripsi data vault gagal: otentikasi tag atau kunci tidak cocok.');
        }

        return (array) json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
    }
}

