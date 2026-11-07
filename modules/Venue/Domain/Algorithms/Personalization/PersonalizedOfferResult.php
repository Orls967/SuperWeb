<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Personalization;

/**
 * Hasil Keputusan Penawaran Promosi Kontekstual On-Site (V16).
 */
final readonly class PersonalizedOfferResult
{
    public function __construct(
        public bool $isEligible,
        public int $discountBps,
        public int $discountedPriceIdr,
        public int $savingsAmountIdr,
        public bool $floorGuardrailTriggered,
        public string $offerDispatchCode,
        public ?string $rejectionReason = null,
        public ?string $certificateSignature = null
    ) {}

    /**
     * Verifikasi integritas tanda tangan digital penawaran.
     */
    public function verifySignature(string $saltSecret): bool
    {
        if ($this->certificateSignature === null) {
            return false;
        }

        $payload = sprintf(
            '%d:%d:%d:%d:%d:%s:%s',
            $this->isEligible ? 1 : 0,
            $this->discountBps,
            $this->discountedPriceIdr,
            $this->savingsAmountIdr,
            $this->floorGuardrailTriggered ? 1 : 0,
            $this->offerDispatchCode,
            $this->rejectionReason ?? 'NONE'
        );

        $expected = hash_hmac('sha256', $payload, $saltSecret);

        return hash_equals($expected, $this->certificateSignature);
    }
}
