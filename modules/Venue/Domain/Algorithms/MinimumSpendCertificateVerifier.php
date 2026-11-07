<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

final class MinimumSpendCertificateVerifier
{
    private const SECRET = 'venue-minimum-spend-settlement-v1';

    public static function sign(int $min, int $dep, int $act, bool $noShow, int $bill, int $cap, int $add, int $short, int $forfeit, int $ref): string
    {
        $noShowStr = $noShow ? '1' : '0';
        $payload = "{$min}:{$dep}:{$act}:{$noShowStr}:{$bill}:{$cap}:{$add}:{$short}:{$forfeit}:{$ref}";

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(MinimumSpendCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->minimumSpendIdr,
            $certificate->depositHeldIdr,
            $certificate->actualSpendIdr,
            $certificate->isNoShow,
            $certificate->totalBillIdr,
            $certificate->capturedDepositIdr,
            $certificate->additionalChargeIdr,
            $certificate->shortfallIdr,
            $certificate->forfeitedDepositIdr,
            $certificate->refundedDepositIdr
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('MinimumSpendCertificate HMAC signature is invalid');
        }

        if ($certificate->isNoShow) {
            if ($certificate->forfeitedDepositIdr + $certificate->refundedDepositIdr !== $certificate->depositHeldIdr) {
                throw new InvalidArgumentException('MinimumSpendCertificate no-show invariant violated: forfeited + refunded != deposit');
            }
        } else {
            if ($certificate->capturedDepositIdr + $certificate->additionalChargeIdr !== $certificate->totalBillIdr) {
                throw new InvalidArgumentException('MinimumSpendCertificate present invariant violated: captured + additional != totalBill');
            }
        }

        return true;
    }
}
