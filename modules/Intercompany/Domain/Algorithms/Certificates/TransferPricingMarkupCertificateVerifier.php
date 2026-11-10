<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms\Certificates;

use Modules\Intercompany\Domain\Exceptions\InvalidCertificateException;

final class TransferPricingMarkupCertificateVerifier
{
    public static function verify(TransferPricingMarkupCertificate $certificate): bool
    {
        return hash_equals($certificate->computeHash(), $certificate->sha256);
    }

    public static function assertValid(TransferPricingMarkupCertificate $certificate): void
    {
        if (! self::verify($certificate)) {
            throw new InvalidCertificateException('Transfer Pricing Markup Certificate hash mismatch.');
        }
    }
}
