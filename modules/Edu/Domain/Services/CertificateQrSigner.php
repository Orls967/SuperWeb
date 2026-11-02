<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Services;

final class CertificateQrSigner
{
    private const SUPPORTED_KEY_IDS = ['v1'];

    /**
     * Generates a signed QR verification token in format: {uuid}.{key_id}.{hmac}
     */
    public function generateToken(string $certUuid, string $keyId = 'v1'): string
    {
        $derivedKey = $this->getDerivedKey($keyId);
        $hmac = hash_hmac('sha256', "{$certUuid}:{$keyId}", $derivedKey);

        return "{$certUuid}.{$keyId}.{$hmac}";
    }

    /**
     * Verifies the authenticity and signature of a QR token.
     */
    public function verifyToken(string $token): bool
    {
        $parsed = $this->parseToken($token);
        if ($parsed === null) {
            return false;
        }

        if (! in_array($parsed['key_id'], self::SUPPORTED_KEY_IDS, true)) {
            return false;
        }

        $derivedKey = $this->getDerivedKey($parsed['key_id']);
        $expectedHmac = hash_hmac('sha256', "{$parsed['uuid']}:{$parsed['key_id']}", $derivedKey);

        return hash_equals($expectedHmac, $parsed['hmac']);
    }

    /**
     * Parses the token into its constituent parts if the basic structure is valid.
     *
     * @return array{uuid: string, key_id: string, hmac: string}|null
     */
    public function parseToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$uuid, $keyId, $hmac] = $parts;

        if (trim($uuid) === '' || trim($keyId) === '' || strlen($hmac) !== 64 || ! ctype_xdigit($hmac)) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'key_id' => $keyId,
            'hmac' => $hmac,
        ];
    }

    private function getDerivedKey(string $keyId): string
    {
        $appKey = (string) config('app.key', 'default-key-for-edu-signature-base64');

        return hash_hmac('sha256', "edu-cert-{$keyId}", $appKey);
    }
}
