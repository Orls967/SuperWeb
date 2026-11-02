<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Services;

use Modules\Venue\Domain\Models\EntertainmentTicket;

class VenueQrSigner
{
    private string $keyId = 'v1';

    private function getDerivedKey(): string
    {
        $appKey = (string) config('app.key', 'base64:testing-venue-ticket-secret-key-32b');

        return hash_hmac('sha256', 'ven-ticket-v1', $appKey);
    }

    public function generatePayload(EntertainmentTicket $ticket): string
    {
        $derivedKey = $this->getDerivedKey();
        $ticketUuid = (string) $ticket->ticket_uuid;
        $dataToSign = "{$ticketUuid}.{$this->keyId}";
        $hmac = hash_hmac('sha256', $dataToSign, $derivedKey);

        return "{$ticketUuid}.{$this->keyId}.{$hmac}";
    }

    /**
     * @return array{ticket_uuid: string, key_id: string}|null
     */
    public function verifyPayload(string $qrPayload): ?array
    {
        $parts = explode('.', $qrPayload);
        if (count($parts) !== 3) {
            return null;
        }

        [$ticketUuid, $keyId, $providedHmac] = $parts;

        if ($keyId !== $this->keyId) {
            return null;
        }

        $derivedKey = $this->getDerivedKey();
        $expectedHmac = hash_hmac('sha256', "{$ticketUuid}.{$keyId}", $derivedKey);

        if (! hash_equals($expectedHmac, $providedHmac)) {
            return null;
        }

        return [
            'ticket_uuid' => $ticketUuid,
            'key_id' => $keyId,
        ];
    }
}
