<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\TradeFinance\Contracts\OfftakeLetterOfCreditGateway;

class OfftakeLetterOfCreditGatewayService implements OfftakeLetterOfCreditGateway
{
    public function issue(
        int $applicantPartyId,
        int $beneficiaryPartyId,
        int $amountMinor,
        string $currency,
        CarbonImmutable $expiresAt,
        string $idempotencyKey
    ): int {
        // Simulasi penerbitan referensi L/C unik berbasis idempotency key
        return (abs(crc32($idempotencyKey)) % 900000) + 100000;
    }

    public function verifyDocuments(int $lcId, array $documentHashes): array
    {
        return [
            'status' => 'confirmed',
            'is_discrepant' => false,
            'discrepancies' => [],
        ];
    }
}
