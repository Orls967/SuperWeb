<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

final class ArtistDoorSplitVerifier
{
    private const SECRET = 'venue-artist-split-deal-secret-v1';

    public static function sign(
        string $dealType,
        int $guarantee,
        int $doorGross,
        int $approvedExp,
        int $shareBps,
        int $whtBps,
        int $advancePaid,
        int $grossPay,
        int $whtIdr,
        int $netPayable,
        int $valasMinorCents
    ): string {
        $payload = implode(':', [
            $dealType,
            $guarantee,
            $doorGross,
            $approvedExp,
            $shareBps,
            $whtBps,
            $advancePaid,
            $grossPay,
            $whtIdr,
            $netPayable,
            $valasMinorCents,
        ]);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(ArtistDoorSplitCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->dealType,
            $certificate->guaranteeIdr,
            $certificate->doorGrossIdr,
            $certificate->approvedExpensesIdr,
            $certificate->shareBps,
            $certificate->whtBps,
            $certificate->advancePaidIdr,
            $certificate->grossPayIdr,
            $certificate->whtIdr,
            $certificate->netPayableIdr,
            $certificate->valasMinorCents
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('ArtistDoorSplitCertificate HMAC signature is invalid or tampered.');
        }

        // Validasi Invarian Matematika
        $doorNet = max(0, $certificate->doorGrossIdr - $certificate->approvedExpensesIdr);
        $doorShare = (int) intdiv($doorNet * $certificate->shareBps, 10_000);

        $expectedGross = match ($certificate->dealType) {
            'guarantee' => $certificate->guaranteeIdr,
            'door_split' => $doorShare,
            'versus' => max($certificate->guaranteeIdr, $doorShare),
            default => throw new InvalidArgumentException("Unknown deal type {$certificate->dealType}"),
        };

        if ($certificate->grossPayIdr !== $expectedGross) {
            throw new InvalidArgumentException("Gross pay mismatch: expected {$expectedGross}, got {$certificate->grossPayIdr}");
        }

        $expectedWht = (int) intdiv($certificate->grossPayIdr * $certificate->whtBps, 10_000);
        if ($certificate->whtIdr !== $expectedWht) {
            throw new InvalidArgumentException("WHT mismatch: expected {$expectedWht}, got {$certificate->whtIdr}");
        }

        $expectedNet = max(0, $certificate->grossPayIdr - $certificate->whtIdr - $certificate->advancePaidIdr);
        if ($certificate->netPayableIdr !== $expectedNet) {
            throw new InvalidArgumentException("Net payable mismatch: expected {$expectedNet}, got {$certificate->netPayableIdr}");
        }

        return true;
    }
}
